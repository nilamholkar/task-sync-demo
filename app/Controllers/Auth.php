<?php

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseController
{
    public function login()
    {
        // If already logged in, don't show login page again
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login');
    }

    public function authenticate()
    {
        $session = session();

        $email = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');

        // Basic validation
        if ($email === '' || $password === '') {
            return redirect()
                ->to('/login')
                ->withInput()
                ->with('error', 'Please enter email and password.');
        }

        // Find active user
        $userModel = new UserModel();

        $user = $userModel
            ->where('email', $email)
            ->where('status', 1)
            ->first();

        // Invalid email
        if (!$user) {
            return redirect()
                ->to('/login')
                ->withInput()
                ->with('error', 'Invalid email or password.');
        }

        // Invalid password
        if (!password_verify($password, $user['password'])) {
            return redirect()
                ->to('/login')
                ->withInput()
                ->with('error', 'Invalid email or password.');
        }

        // Regenerate session ID after successful login
        $session->regenerate(true);

        // Store logged-in user information
        $session->set([
            'user_id'     => $user['id'],
            'user_name'   => $user['name'],
            'user_email'  => $user['email'],
            'user_role'   => $user['role'],
            'isLoggedIn'  => true,
        ]);

        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()
            ->to('/login')
            ->with('success', 'You have been logged out successfully.');
    }
}
