<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= esc($title) ?> - Task Management</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">

    <div class="container">

        <a class="navbar-brand" href="<?= site_url('dashboard') ?>">
            Task Management
        </a>

        <a
            href="<?= site_url('users') ?>"
            class="btn btn-outline-light btn-sm">
            Back to Users
        </a>

    </div>

</nav>


<div class="container mt-4">

    <div class="row justify-content-center">

        <div class="col-md-7">

            <div class="card shadow-sm">

                <div class="card-header">

                    <h4 class="mb-0">
                        <?= esc($title) ?>
                    </h4>

                </div>

                <div class="card-body">


                    <?php if (session()->getFlashdata('error')): ?>

                        <div class="alert alert-danger">
                            <?= esc(session()->getFlashdata('error')) ?>
                        </div>

                    <?php endif; ?>


                    <?php if (session()->getFlashdata('errors')): ?>

                        <div class="alert alert-danger">

                            <?php foreach (session()->getFlashdata('errors') as $error): ?>

                                <div>
                                    <?= esc($error) ?>
                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>


                    <?php

                    $isEdit = !empty($user);

                    $formAction = $isEdit
                        ? site_url('users/update/' . $user['id'])
                        : site_url('users/store');

                    ?>


                    <form
                        method="post"
                        action="<?= $formAction ?>">

                        <?= csrf_field() ?>


                        <!-- Name -->

                        <div class="mb-3">

                            <label class="form-label">
                                Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="<?= esc(old('name', $user['name'] ?? '')) ?>"
                                required>

                        </div>


                        <!-- Email -->

                        <div class="mb-3">

                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?= esc(old('email', $user['email'] ?? '')) ?>"
                                required>

                        </div>


                        <!-- Password -->

                        <div class="mb-3">

                            <label class="form-label">

                                Password

                                <?php if ($isEdit): ?>

                                    <small class="text-muted">
                                        Leave blank to keep existing password.
                                    </small>

                                <?php endif; ?>

                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                <?= $isEdit ? '' : 'required' ?>>

                        </div>


                        <!-- Role -->

                        <div class="mb-3">

                            <label class="form-label">
                                Role
                            </label>

                            <select
                                name="role"
                                class="form-select"
                                required>

                                <option value="user"
                                    <?= old('role', $user['role'] ?? 'user') === 'user'
                                        ? 'selected'
                                        : '' ?>>
                                    User
                                </option>

                                <option value="admin"
                                    <?= old('role', $user['role'] ?? '') === 'admin'
                                        ? 'selected'
                                        : '' ?>>
                                    Admin
                                </option>

                            </select>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            <?= $isEdit ? 'Update User' : 'Create User' ?>

                        </button>


                        <a
                            href="<?= site_url('users') ?>"
                            class="btn btn-secondary">

                            Cancel

                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>