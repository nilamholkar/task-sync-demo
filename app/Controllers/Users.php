<?php

namespace App\Controllers;

use App\Models\UserModel;

class Users extends BaseController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    private function checkLogin()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login');
        }

        return null;
    }

    public function index()
    {
        if ($redirect = $this->checkLogin()) {
            return $redirect;
        }

        $data = [
            'title' => 'Users',
            'users' => $this->userModel
                ->orderBy('id', 'DESC')
                ->findAll()
        ];

        return view('users/index', $data);
    }

    public function create()
    {
        if ($redirect = $this->checkLogin()) {
            return $redirect;
        }

        return view('users/form', [
            'title' => 'Add User',
            'user' => null
        ]);
    }

    public function store()
    {
        if ($redirect = $this->checkLogin()) {
            return $redirect;
        }

        $validation = \Config\Services::validation();

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[150]',
            'password' => 'required|min_length[6]',
            'role' => 'required|in_list[admin,user]',
        ];

        if (!$this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        // Check duplicate email
        $existing = $this->userModel
            ->where('email', $this->request->getPost('email'))
            ->first();

        if ($existing) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Email address already exists.');
        }

        $this->userModel->insert([
            'name' => trim($this->request->getPost('name')),
            'email' => trim($this->request->getPost('email')),
            'password' => password_hash(
                $this->request->getPost('password'),
                PASSWORD_DEFAULT
            ),
            'role' => $this->request->getPost('role'),
            'status' => 1
        ]);

        return redirect()
            ->to('/users')
            ->with('success', 'User created successfully.');
    }

    public function edit($id)
    {
        if ($redirect = $this->checkLogin()) {
            return $redirect;
        }

        $user = $this->userModel->find($id);

        if (!$user) {
            return redirect()
                ->to('/users')
                ->with('error', 'User not found.');
        }

        return view('users/form', [
            'title' => 'Edit User',
            'user' => $user
        ]);
    }

    public function update($id)
    {
        if ($redirect = $this->checkLogin()) {
            return $redirect;
        }

        $user = $this->userModel->find($id);

        if (!$user) {
            return redirect()
                ->to('/users')
                ->with('error', 'User not found.');
        }

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[150]',
            'role' => 'required|in_list[admin,user]',
        ];

        if (!$this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $email = trim($this->request->getPost('email'));

        // Check duplicate email
        $existing = $this->userModel
            ->where('email', $email)
            ->where('id !=', $id)
            ->first();

        if ($existing) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Email address already exists.');
        }

        $updateData = [
            'name' => trim($this->request->getPost('name')),
            'email' => $email,
            'role' => $this->request->getPost('role')
        ];

        // Update password only if entered
        $password = $this->request->getPost('password');

        if (!empty($password)) {
            if (strlen($password) < 6) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Password must contain at least 6 characters.');
            }

            $updateData['password'] = password_hash(
                $password,
                PASSWORD_DEFAULT
            );
        }

        $this->userModel->update($id, $updateData);

        return redirect()
            ->to('/users')
            ->with('success', 'User updated successfully.');
    }

    public function delete($id)
    {
        if ($redirect = $this->checkLogin()) {
            return $redirect;
        }

        // Don't allow deleting yourself
        if ((int) session()->get('user_id') === (int) $id) {
            return redirect()
                ->to('/users')
                ->with('error', 'You cannot delete your own account.');
        }

        $user = $this->userModel->find($id);

        if (!$user) {
            return redirect()
                ->to('/users')
                ->with('error', 'User not found.');
        }

        $this->userModel->delete($id);

        return redirect()
            ->to('/users')
            ->with('success', 'User deleted successfully.');
    }

    public function toggleStatus($id)
    {
        if ($redirect = $this->checkLogin()) {
            return $redirect;
        }

        // Don't deactivate yourself
        if ((int) session()->get('user_id') === (int) $id) {
            return redirect()
                ->to('/users')
                ->with('error', 'You cannot deactivate your own account.');
        }

        $user = $this->userModel->find($id);

        if (!$user) {
            return redirect()
                ->to('/users')
                ->with('error', 'User not found.');
        }

        $newStatus = $user['status'] == 1 ? 0 : 1;

        $this->userModel->update($id, [
            'status' => $newStatus
        ]);

        $message = $newStatus == 1
            ? 'User activated successfully.'
            : 'User deactivated successfully.';

        return redirect()
            ->to('/users')
            ->with('success', $message);
    }
}