<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validación extra: asegúrate de que el email no esté en uso
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser) {
                $this->addFlash('error', 'El email ya está registrado.');
                return $this->redirectToRoute('app_register');
            }

            // Manejo de la contraseña y persistencia en la base de datos
            try {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );

                $errors = $validator->validate($user);
                if (count($errors) > 0) {
                    foreach ($errors as $error) {
                        $this->addFlash('error', $error->getMessage());
                    }
                    return $this->redirectToRoute('app_register');
                }

                $entityManager->persist($user);
                $entityManager->flush();

                return $this->redirectToRoute('app_home');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Hubo un problema al registrar al usuario.');
                return $this->redirectToRoute('app_register');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/apiregister', name: 'api_register')]
    public function apiregister(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, JWTTokenManagerInterface $jwtManager): Response
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['email']) || !isset($data['password'])) {
            return $this->json(['error' => 'Datos inválidos'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);

        // Validación de que los datos existan y no estén vacíos
        if (!$data || !isset($data['email']) || !isset($data['password']) || empty(trim($data['email'])) || empty(trim($data['password']))) {
            return $this->json(['error' => 'Datos inválidos. Los campos email y contraseña no deben estar vacíos.'], 400);
        }

        // Verificación de que el email no esté en uso
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            return $this->json(['error' => 'El email ya está registrado'], 400);
        }

        try {
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $data['password']
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();

            $token = $jwtManager->create($user);

            return $this->json(['token' => $token], 200, ['Access-Control-Allow-Origin' => '*']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Hubo un problema al registrar al usuario'], 500);
        }
    }
}
