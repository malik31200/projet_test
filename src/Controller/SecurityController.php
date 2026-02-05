<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name:'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if already connected, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Retrieve the connection error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last email entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        // if already connected, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');
            $firstName = $request->request->get('first_name');
            $lastName = $request->request->get('last_name');

            // Basic validation
            $errors = [];

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Veuillez entrer un email valide";
            }

            if (empty($password) || strlen($password) < 6) {
                $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
            }

            if ($password !== $confirmPassword) {
                $errors[] = "Les mots de passes ne correspondent pas";
            }

            if (empty($firstName) || empty($lastName)) {
                $errors[] = "Le prénom et le nom sont obligatoires";
            }

            // Check if email is existing
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $errors[] = "Cet email est déjà utilisé";
            }

            if (empty($errors)) {
                //Create a new user
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setRoles(['ROLE_USER']);

                // Hash the passwors
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);

                // save to basedata
                $em->persist($user);
                $em->flush();

                // success message
                $this->addFlash('success', 'Inscription réussie! Vous pouvez maintenant vous connecter');

                return $this->redirectToRoute('app_login');
            }

            // Displays the errors
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }
        return $this->render('security/register.html.twig');
    }
        #[Route('/logout', name: 'app_logout')]
        public function logout(): void
        {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
