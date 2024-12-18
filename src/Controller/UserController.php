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
use Symfony\Component\Security\Core\Security;



class UserController extends AbstractController
{
    // #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    // public function register(
    //     Request $request,
    //     EntityManagerInterface $em,
    //     ValidatorInterface $validator,
    //     UserPasswordHasherInterface $passwordHasher
    // ): JsonResponse {
    //     $data = json_decode($request->getContent(), true);

    //     $nom = $data['nom'] ?? null;
    //     $prenom = $data['prenom'] ?? null;
    //     $email = $data['email'] ?? null;
    //     $password = $data['password'] ?? null;

    //     if (!$nom || !$prenom || !$email || !$password) {
    //         return $this->json(['error' => 'Tous les champs sont obligatoires'], 400);
    //     }

    //     $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
    //     if ($existingUser) {
    //         return $this->json(['error' => 'Un utilisateur avec cet email existe déjà.'], 400);
    //     }

    //     $user = new User();
    //     $user->setNom($nom);
    //     $user->setPrenom($prenom);
    //     $user->setEmail($email);
    //     $hashedPassword = $passwordHasher->hashPassword($user, $password);
    //     $user->setPassword($hashedPassword);

    //     $errors = $validator->validate($user);
    //     if (count($errors) > 0) {
    //         return $this->json(['error' => (string) $errors], 400);
    //     }

    //     $em->persist($user);
    //     $em->flush();

    //     $emailToken = new EmailToken();
    //     $emailToken->setUser($user);
    //     $emailToken->setToken(bin2hex(random_bytes(16)));
    //     $emailToken->setCreatedAt(new \DateTime());
    //     $emailToken->setExpiresAt((new \DateTime())->modify('+1 hour'));

    //     $em->persist($emailToken);
    //     $em->flush();

    //     // TODO: Send email with token

    //     return $this->json(['message' => 'Utilisateur enregistré. Vérifiez votre email pour valider votre compte.'], 201);
    // }

    #[Route('/api/validate-email/{token}', name: 'api_validate_email', methods: ['GET'])]
    public function validateEmail(string $token, EntityManagerInterface $em): JsonResponse
    {
        // Nettoyage supplémentaire pour éviter les retours à la ligne ou espaces inutiles
        $token = trim($token);

        // Rechercher le token dans la base de données
        $emailToken = $em->getRepository(EmailToken::class)->findOneBy(['token' => $token]);

        if (!$emailToken) {
            return $this->json(['error' => 'Token invalide ou expiré'], 400);
        }

        if ($emailToken->getExpiresAt() < new \DateTime()) {
            return $this->json(['error' => 'Token expiré'], 400);
        }

        $user = $emailToken->getUser();
        $user->setActive(true); // Activer l'utilisateur
        $em->flush();

        // Optionnel: Supprimer le token une fois qu'il a été utilisé
        $em->remove($emailToken);
        $em->flush();

        return $this->json(['message' => 'Votre email a été validé avec succès. Vous pouvez maintenant vous connecter.'], 200);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register (
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        EmailService $emailService // Injection du service EmailService
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

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], 400);
        }

        $em->persist($user);
        $em->flush();

        // Création du token de validation d'email
        $emailToken = new EmailToken();
        $emailToken->setUser($user);
        $emailToken->setToken(bin2hex(random_bytes(16)));
        $emailToken->setCreatedAt(new \DateTime());
        $emailToken->setExpiresAt((new \DateTime())->modify('+1 hour'));

        $em->persist($emailToken);
        $em->flush();

        // Envoi de l'email de confirmation avec le token
        $emailService->sendEmailConfirmation($user->getEmail(), $emailToken);

        return $this->json(['message' => 'Utilisateur enregistré. Vérifiez votre email pour valider votre compte.'], 201);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['error' => 'Email et mot de passe sont obligatoires'], 400);
        }

        // Recherche de l'utilisateur par son email
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Identifiants invalides'], 400);
        }

        // Vérification si l'utilisateur est actif
        if (!$user->isActive()) {
            return $this->json(['error' => 'Veuillez vérifier votre email avant de vous connecter.'], 400);
        }

        // Créer un token JWT ou un autre type de session (par exemple, cookies)
        $token = 'JWT-EXAMPLE-TOKEN'; // Exemple de token

        return $this->json(['message' => 'Connexion réussie', 'token' => $token], 200);
    }
}
