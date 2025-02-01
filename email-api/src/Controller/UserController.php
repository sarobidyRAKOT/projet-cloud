<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\EmailToken;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Psr\Log\LoggerInterface;

class UserController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        EmailService $emailService,
        SessionInterface $session,
        LoggerInterface $logger // Ajout du logger
    ): JsonResponse {
        date_default_timezone_set('Indian/Antananarivo');

        // Récupération des données
        $data = json_decode($request->getContent(), true);
        if (!isset($data['nom'], $data['prenom'], $data['email'], $data['password'])) {
            $logger->warning('Inscription échouée: champs manquants.', ['data' => $data]);
            return $this->json(['error' => 'Tous les champs sont obligatoires'], 400);
        }

        // Vérification de l'existence de l'utilisateur
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            $logger->warning('Un utilisateur existe déjà avec cet email.', ['email' => $data['email']]);
            return $this->json(['error' => 'Un utilisateur avec cet email existe déjà.'], 400);
        }

        // Création de l'utilisateur
        $user = new User();
        $user->setNom($data['nom']);
        $user->setPrenom($data['prenom']);
        $user->setEmail($data['email']);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setActive(false);

        // Validation des données
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $logger->warning('Erreur de validation pour l\'utilisateur.', ['errors' => (string) $errors]);
            return $this->json(['error' => (string) $errors], 400);
        }

        // Sauvegarde de l'utilisateur
        $em->persist($user);
        $em->flush();

        // Génération du code PIN et de l'expiration
        $codePin = random_int(100000, 999999);
        $expirationTimestamp = time() + 3000;

        $session->start();

        // Stockage dans la session
        $session->set('code_pin', $codePin);
        $session->set('code_expiration', $expirationTimestamp);
        $session->set('code_creation_time', time());

        // Récupération des valeurs
        $sessionCodePin = $session->get('code_pin');
        $codeCreation = $session->get('code_creation_time');
        $codeExpiration = $session->get('code_expiration');
        $codePin = $session->get('code_pin');

        // Log des valeurs récupérées
        error_log('Test Log - Code PIN: ' . ($sessionCodePin ?? 'Non défini'));
        error_log('Test Log - Creation Time: ' . ($codeCreation ? date('Y-m-d H:i:s', $codeCreation) : 'Non défini'));
        error_log('Test Log - Expiration Time: ' . ($codeExpiration ? date('Y-m-d H:i:s', $codeExpiration) : 'Non défini'));
        error_log('Test Log - code Pin dans session: ' . ($codePin ?? 'Non défini'));


        // error_log('Session actuelle: ' . print_r($session->all(), true));




        // Envoi de l'email de confirmation
        $emailToken = new EmailToken();
        $emailToken->setUser($user);
        $emailToken->setToken(bin2hex(random_bytes(16)));
        $emailToken->setCreatedAt(new \DateTime());
        $emailToken->setExpiresAt((new \DateTime())->modify('+1 hour'));

        $em->persist($emailToken);
        $em->flush();

        $emailService->sendEmailConfirmation($user->getEmail(), $emailToken, $codePin);

        $logger->info('Utilisateur inscrit avec succès et email envoyé.', ['email' => $data['email']]);

        return $this->json(['message' => 'Utilisateur enregistré. Un email avec un code PIN a été envoyé.'], 201);
    }

    #[Route('/api/regenerate-pin', name: 'api_regenerate_pin', methods: ['POST'])]
    public function regeneratePin(
        Request $request,
        SessionInterface $session,
        EmailService $emailService,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['error' => 'Email requis pour régénérer le code PIN.'], 400);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        // Générer un nouveau code PIN
        $newCodePin = random_int(100000, 999999);
        $session->set('code_pin', $newCodePin);
        $session->set('code_expiration', time() + 3000);  // 50 minutes

        error_log('Nouveau Code PIN généré : ' . $newCodePin); // 90 secondes d'expiration

        // Récupérer le token d'email existant
        $emailToken = $em->getRepository(EmailToken::class)->findOneBy(['user' => $user]);
        if (!$emailToken) {
            return $this->json(['error' => 'Aucun token trouvé pour cet utilisateur.'], 404);
        }

        // Réenvoyer l'email avec le nouveau code PIN
        $emailService->sendEmailConfirmation($user->getEmail(), $emailToken, $newCodePin);

        return $this->json(['message' => 'Nouveau code PIN envoyé avec succès.'], 200);
    }

    // #[Route('/api/login/first', name: 'api_login', methods: ['POST'])]
    // public function login(
    //     Request $request,
    //     UserPasswordHasherInterface $passwordHasher,
    //     SessionInterface $session,
    //     EntityManagerInterface $em
    // ): JsonResponse {
    //     $data = json_decode($request->getContent(), true);

    //     $email = $data['email'] ?? null;
    //     $password = $data['password'] ?? null;
    //     $codePin = $data['code_pin'] ?? null;

    //     if (!$email || !$password || !$codePin) {
    //         return $this->json(['error' => 'Tous les champs sont obligatoires.'], 400);
    //     }

    //     $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

    //     if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
    //         return $this->json(['error' => 'Identifiants invalides.'], 400);
    //     }

    //     // Vérifier si l'utilisateur est actif
    //     if (!$user->isActive()) {
    //         return $this->json(['error' => 'Votre compte est inactif. Veuillez contacter l\'administrateur.'], 403);
    //     }

    //     // Vérifier le code PIN
    //     $sessionCodePin = $session->get('code_pin');
    //     $codeExpiration = $session->get('code_expiration');

    //     if (time() > $codeExpiration) {
    //         return $this->json(['error' => 'Code PIN expiré. Veuillez demander un nouveau code.'], 400);
    //     }

    //     if ((int) $sessionCodePin !== (int) $codePin) {
    //         return $this->json(['error' => 'Code PIN invalide.'], 400);
    //     }

    //     return $this->json(['message' => 'Connexion réussie.'], 200);
    // }

    #[Route('/api/login/first', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        SessionInterface $session,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ): JsonResponse {
        // Enregistrer la date et l'heure de l'appel de l'API
        
        // Définir le fuseau horaire
        date_default_timezone_set('Indian/Antananarivo');
        $dateAppelAPI = date('Y-m-d H:i:s');

        // Loguer la date et l'heure de l'appel de l'API
        $logger->info('Appel de l\'API de connexion', ['date_appel' => $dateAppelAPI]);

        // Récupération des données envoyées via POST
        $data = json_decode($request->getContent(), true);
        if (!isset($data['email'], $data['password'], $data['code_pin'])) {
            // Loguer si des champs manquent
            $logger->warning('Tentative de connexion échouée: champs manquants.', ['data' => $data, 'date_appel' => $dateAppelAPI]);
            return $this->json(['error' => 'Tous les champs sont obligatoires.'], 400);
        }

        // Vérification de l'utilisateur par email et mot de passe
        $user = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            // Loguer si les identifiants sont incorrects
            $logger->warning('Identifiants invalides pour l\'utilisateur.', ['email' => $data['email'], 'date_appel' => $dateAppelAPI]);
            return $this->json(['error' => 'Identifiants invalides.'], 400);
        }

        // Vérification si le compte est actif
        if (!$user->isActive()) {
            // Loguer si le compte est inactif
            $logger->warning('Compte inactif pour l\'utilisateur.', ['email' => $data['email'], 'date_appel' => $dateAppelAPI]);
            return $this->json(['error' => 'Votre compte est inactif. Veuillez contacter l\'administrateur.'], 403);
        }

        $session->start();

        // Récupération du code PIN et de l'expiration depuis la session
        $sessionCodePin = $session->get('code_pin');
        $codeExpiration = $session->get('code_expiration');

        // Loguer l'état actuel des données de session avec les dates
        error_log('Test Log - Code PIN: ' . ($sessionCodePin ?? 'Non défini'));
        error_log('Test Log - Expiration Time: ' . ($codeExpiration ? date('Y-m-d H:i:s', $codeExpiration) : 'Non défini'));

        // Vérification si le code PIN et son expiration existent dans la session
        if (!$sessionCodePin || !$codeExpiration) {
            // Loguer l'absence de code PIN ou expiration dans la session
            $logger->error('Code PIN absent ou expiré dans la session pour l\'utilisateur.', ['email' => $data['email'], 'date_appel' => $dateAppelAPI]);
            return $this->json(['error' => 'Code PIN expiré ou invalide.'], 400);
        }

        // Vérification de l'expiration du code PIN
        if (time() > $codeExpiration) {
            // Loguer l'expiration du code PIN
            $logger->error('Code PIN expiré pour l\'utilisateur.', ['email' => $data['email'], 'date_appel' => $dateAppelAPI]);
            return $this->json(['error' => 'Code PIN expiré.'], 400);
        }

        // Vérification du code PIN envoyé par l'utilisateur
        if ((int) $sessionCodePin !== (int) $data['code_pin']) {
            // Loguer l'erreur de code PIN incorrect
            $logger->error('Code PIN incorrect pour l\'utilisateur.', ['email' => $data['email'], 'date_appel' => $dateAppelAPI]);
            return $this->json(['error' => 'Code PIN incorrect.'], 400);
        }

        // Connexion réussie, marquer le premier login comme terminé
        $session->set('isFirstLoginCompleted', true);
        $session->save();
        
        // Loguer la réussite de la connexion
        $logger->info('Connexion réussie pour l\'utilisateur.', ['email' => $data['email'], 'date_appel' => $dateAppelAPI]);

        // Retourner une réponse JSON avec la date actuelle d'Apple (ou votre date) incluse
        return $this->json([
            'message' => 'Connexion réussie.',
            'date' => date('Y-m-d H:i:s'), // Date actuelle dans le format souhaité
            'date_appel' => $dateAppelAPI // Ajouter la date d'appel de l'API à la réponse
        ], 200);
    }


    #[Route('/api/validate-email/{token}', name: 'api_validate_email', methods: ['GET'])]
    public function validateEmail(string $token, EntityManagerInterface $em): JsonResponse
    {
        $emailToken = $em->getRepository(EmailToken::class)->findOneBy(['token' => $token]);

        if (!$emailToken || $emailToken->getExpiresAt() < new \DateTime()) {
            return $this->json(['error' => 'Token invalide ou expiré.'], 400);
        }

        $user = $emailToken->getUser();
        $user->setActive(true); // Activer le compte
        $em->remove($emailToken);
        $em->flush();

        return $this->json(['message' => 'Compte activé avec succès.'], 200);
    }


    #[Route('/api/update-profile', name: 'api_update_profile', methods: ['POST'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Récupération de l'email fourni par l'utilisateur
        $email = $data['email'] ?? null;
        $nom = $data['nom'] ?? null;
        $prenom = $data['prenom'] ?? null;
        $password = $data['password'] ?? null;

        // Vérifier si l'email est fourni
        if (!$email) {
            return $this->json(['error' => 'Email requis pour mettre à jour le profil.'], 400);
        }

        // Vérifier si l'utilisateur existe
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        // Mise à jour des champs autorisés
        if ($nom) {
            $user->setNom($nom);
        }
        if ($prenom) {
            $user->setPrenom($prenom);
        }
        if ($password) {
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        }

        // Sauvegarde des modifications
        $em->persist($user);
        $em->flush();

        return $this->json(['message' => 'Profil mis à jour avec succès.'], 200);
    }

    // #[Route('/api/login/next', name: 'api_login_next', methods: ['POST'])]
    // public function loginNext(
    //     Request $request,
    //     UserPasswordHasherInterface $passwordHasher,
    //     SessionInterface $session,
    //     EntityManagerInterface $em
    // ): JsonResponse {
    //     $data = json_decode($request->getContent(), true);

    //     $email = $data['email'] ?? null;
    //     $password = $data['password'] ?? null;

    //     if (!$email || !$password) {
    //         return $this->json(['error' => 'Tous les champs sont obligatoires.'], 400);
    //     }

    //     // Gestion des tentatives via la session
    //     $loginAttemptsKey = "login_attempts_$email";
    //     $isLockedKey = "is_locked_$email";
    //     $lockTimeoutKey = "lock_timeout_$email";
    //     $maxAttempts = 3;
    //     $lockDuration = 300; // 5 minutes

    //     // Vérifier si le compte est bloqué
    //     if ($session->get($isLockedKey, false)) {
    //         $lockTimeout = $session->get($lockTimeoutKey, 0);
    //         if (time() < $lockTimeout) {
    //             $remainingTime = $lockTimeout - time();
    //             return $this->json([
    //                 'error' => 'Compte temporairement bloqué.',
    //                 'remainingTime' => $remainingTime
    //             ], 403);
    //         } else {
    //             // Débloquer le compte après expiration
    //             $session->remove($isLockedKey);
    //             $session->remove($lockTimeoutKey);
    //             $session->set($loginAttemptsKey, 0);
    //         }
    //     }

    //     // Vérifier l'utilisateur
    //     $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
    //     if (!$user) {
    //         return $this->json(['error' => 'Utilisateur introuvable.'], 404);
    //     }

    //     // Vérifier si l'utilisateur est actif
    //     if (!$user->isActive()) {
    //         return $this->json(['error' => 'Votre compte est inactif. Veuillez contacter l\'administrateur.'], 403);
    //     }

    //     // Vérifier le mot de passe
    //     if (!$passwordHasher->isPasswordValid($user, $password)) {
    //         $attempts = $session->get($loginAttemptsKey, 0) + 1;
    //         $session->set($loginAttemptsKey, $attempts);

    //         if ($attempts >= $maxAttempts) {
    //             $session->set($isLockedKey, true);
    //             $session->set($lockTimeoutKey, time() + $lockDuration);
    //             return $this->json([
    //                 'error' => 'Compte bloqué. Trop de tentatives échouées.',
    //                 'lockDuration' => $lockDuration
    //             ], 403);
    //         }

    //         return $this->json([
    //             'error' => 'Mot de passe incorrect.',
    //             'remainingAttempts' => $maxAttempts - $attempts
    //         ], 403);
    //     }

    //     // Connexion réussie : réinitialiser les tentatives
    //     $session->remove($loginAttemptsKey);
    //     $session->remove($isLockedKey);
    //     $session->remove($lockTimeoutKey);

    //     return $this->json(['message' => 'Connexion réussie.'], 200);
    // }


    #[Route('/api/login/next', name: 'api_login_next', methods: ['POST'])]
    public function loginNext(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        SessionInterface $session,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        // Vérifier si l'utilisateur a complété l'étape initiale
        if (!$session->has('isFirstLoginCompleted') || !$session->get('isFirstLoginCompleted')) {
            return $this->json(['error' => 'L\'étape initiale de connexion n\'a pas été complétée.'], 403);
        }

        if (!$email || !$password) {
            return $this->json(['error' => 'Tous les champs sont obligatoires.'], 400);
        }

        // Gestion des tentatives via la session
        $loginAttemptsKey = "login_attempts_$email";
        $isLockedKey = "is_locked_$email";
        $lockTimeoutKey = "lock_timeout_$email";
        $maxAttempts = 3; // Nombre maximum de tentatives
        $lockDuration = 300; // Durée du blocage en secondes (5 minutes)

        // Vérifier si le compte est temporairement bloqué
        if ($session->get($isLockedKey, false)) {
            $lockTimeout = $session->get($lockTimeoutKey, 0);
            if (time() < $lockTimeout) {
                $remainingTime = $lockTimeout - time();
                return $this->json([
                    'error' => 'Compte temporairement bloqué.',
                    'remainingTime' => $remainingTime
                ], 403);
            } else {
                // Débloquer le compte après expiration
                $session->remove($isLockedKey);
                $session->remove($lockTimeoutKey);
                $session->set($loginAttemptsKey, 0);
            }
        }

        // Vérifier si l'utilisateur existe dans la base de données
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        // Vérifier si le compte de l'utilisateur est actif
        if (!$user->isActive()) {
            return $this->json(['error' => 'Votre compte est inactif. Veuillez contacter l\'administrateur.'], 403);
        }

        // Vérifier le mot de passe
        if (!$passwordHasher->isPasswordValid($user, $password)) {
            $attempts = $session->get($loginAttemptsKey, 0) + 1;
            $session->set($loginAttemptsKey, $attempts);

            if ($attempts >= $maxAttempts) {
                // Bloquer le compte
                $session->set($isLockedKey, true);
                $session->set($lockTimeoutKey, time() + $lockDuration);
                return $this->json([
                    'error' => 'Compte bloqué. Trop de tentatives échouées.',
                    'lockDuration' => $lockDuration
                ], 403);
            }

            return $this->json([
                'error' => 'Mot de passe incorrect.',
                'remainingAttempts' => $maxAttempts - $attempts
            ], 403);
        }

        // Réinitialiser les tentatives après une connexion réussie
        $session->remove($loginAttemptsKey);
        $session->remove($isLockedKey);
        $session->remove($lockTimeoutKey);

        // Connexion réussie
        return $this->json(['message' => 'Connexion réussie.'], 200);
    }
}
