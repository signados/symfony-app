<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegisterTest extends WebTestCase
{
    public function testApiRegisterSuccess(): void
    {
        $client = static::createClient();
        $client->request('POST', '/apiregister', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'testuser@example.com',
            'password' => 'password123'
        ]));

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertJson($client->getResponse()->getContent());

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $responseData);
    }

    public function testApiRegisterInvalidData(): void
    {
        $client = static::createClient();
        // Enviamos un email vacío y un password
        $client->request('POST', '/apiregister', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => '',
            'password' => 'password123'
        ]));

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertJson($client->getResponse()->getContent());

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Datos inválidos. Los campos email y contraseña no deben estar vacíos.', $responseData['error']);
    }

    public function testApiRegisterDuplicateEmail(): void
    {
        $client = static::createClient();

        // Registramos un usuario exitosamente
        $client->request('POST', '/apiregister', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'duplicate@example.com',
            'password' => 'password123'
        ]));
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Intentamos registrar con el mismo email
        $client->request('POST', '/apiregister', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'duplicate@example.com',
            'password' => 'password123'
        ]));

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $this->assertJson($client->getResponse()->getContent());

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('El email ya está registrado', $responseData['error']);
    }
}
