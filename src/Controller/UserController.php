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

class UserController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        EmailService $emailService,
        SessionInterface $session
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $nom = $data['nom'] ?? null;
        $prenom = $data['prenom'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$nom || !$prenom || !$email || !$password) {
            return $this->json(['error' => 'Tous les champs sont obligatoires'], 400);
        }

        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return $this->json(['error' => 'Un utilisateur avec cet email existe déjà.'], 400);
        }

        $user = new User();
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setActive(false); // Compte inactif par défaut

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], 400);
        }

        $em->persist($user);
        $em->flush();

        // Générer le code PIN aléatoire
        $codePin = random_int(100000, 999999);
        $session->set('code_pin', $codePin);
        $session->set('code_expiration', time() + 90); 
        error_log('Code PIN généré : ' . $codePin);

        // Créer le token de validation
        $emailToken = new EmailToken();
        $emailToken->setUser($user);
        $emailToken->setToken(bin2hex(random_bytes(16)));
        $emailToken->setCreatedAt(new \DateTime());
        $emailToken->setExpiresAt((new \DateTime())->modify('+1 hour'));
        $em->persist($emailToken);
        $em->flush();

        // Envoi de l'email
        $emailService->sendEmailConfirmation($user->getEmail(), $emailToken, $codePin);

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
        $session->set('code_expiration', time() + 90);
        dump($newCodePin); // 90 secondes d'expiration

        // Récupérer le token d'email existant
        $emailToken = $em->getRepository(EmailToken::class)->findOneBy(['user' => $user]);
        if (!$emailToken) {
            return $this->json(['error' => 'Aucun token trouvé pour cet utilisateur.'], 404);
        }

        // Réenvoyer l'email avec le nouveau code PIN
        $emailService->sendEmailConfirmation($user->getEmail(), $emailToken, $newCodePin);

        return $this->json(['message' => 'Nouveau code PIN envoyé avec succès.'], 200);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        SessionInterface $session,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $codePin = $data['code_pin'] ?? null;

        if (!$email || !$password || !$codePin) {
            return $this->json(['error' => 'Tous les champs sont obligatoires.'], 400);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Identifiants invalides.'], 400);
        }

        // Vérifier le code PIN
        $sessionCodePin = $session->get('code_pin');
        $codeExpiration = $session->get('code_expiration');

        if (time() > $codeExpiration) {
            return $this->json(['error' => 'Code PIN expiré. Veuillez demander un nouveau code.'], 400);
        }

        if ((int) $sessionCodePin !== (int) $codePin) {
            return $this->json(['error' => 'Code PIN invalide.'], 400);
        }

        return $this->json(['message' => 'Connexion réussie.'], 200);
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

    if (!$email || !$password) {
        return $this->json(['error' => 'Tous les champs sont obligatoires.'], 400);
    }

    // Gestion des tentatives via la session
    $loginAttemptsKey = "login_attempts_$email";
    $isLockedKey = "is_locked_$email";
    $lockTimeoutKey = "lock_timeout_$email";
    $maxAttempts = 3;
    $lockDuration = 300; // 5 minutes

    // Vérifier si le compte est bloqué
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

    // Vérifier l'utilisateur
    $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
    if (!$user) {
        return $this->json(['error' => 'Utilisateur introuvable.'], 404);
    }

    // Vérifier le mot de passe
    if (!$passwordHasher->isPasswordValid($user, $password)) {
        $attempts = $session->get($loginAttemptsKey, 0) + 1;
        $session->set($loginAttemptsKey, $attempts);

        if ($attempts >= $maxAttempts) {
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

    // Connexion réussie : réinitialiser les tentatives
    $session->remove($loginAttemptsKey);
    $session->remove($isLockedKey);
    $session->remove($lockTimeoutKey);

    return $this->json(['message' => 'Connexion réussie.'], 200);
}


}
