<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Users - Task Management</title>

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

        <div class="d-flex align-items-center">

            <span class="text-white me-3">
                <?= esc(session()->get('user_name')) ?>
            </span>

            <a
                href="<?= site_url('logout') ?>"
                class="btn btn-outline-light btn-sm">
                Logout
            </a>

        </div>

    </div>

</nav>


<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h2>Users</h2>

        <a
            href="<?= site_url('users/create') ?>"
            class="btn btn-primary">
            + Add User
        </a>

    </div>


    <?php if (session()->getFlashdata('success')): ?>

        <div class="alert alert-success">
            <?= esc(session()->getFlashdata('success')) ?>
        </div>

    <?php endif; ?>


    <?php if (session()->getFlashdata('error')): ?>

        <div class="alert alert-danger">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>

    <?php endif; ?>


    <div class="card shadow-sm">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>#</th>

                            <th>Name</th>

                            <th>Email</th>

                            <th>Role</th>

                            <th>Status</th>

                            <th width="250">Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!empty($users)): ?>

                        <?php foreach ($users as $user): ?>

                            <tr>

                                <td>
                                    <?= esc($user['id']) ?>
                                </td>

                                <td>
                                    <?= esc($user['name']) ?>
                                </td>

                                <td>
                                    <?= esc($user['email']) ?>
                                </td>

                                <td>

                                    <?php if ($user['role'] === 'admin'): ?>

                                        <span class="badge bg-primary">
                                            Admin
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            User
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if ($user['status'] == 1): ?>

                                        <span class="badge bg-success">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-danger">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <a
                                        href="<?= site_url('users/edit/' . $user['id']) ?>"
                                        class="btn btn-sm btn-warning">
                                        Edit
                                    </a>

                                    <a
                                        href="<?= site_url('users/toggle-status/' . $user['id']) ?>"
                                        class="btn btn-sm btn-info">
                                        <?= $user['status'] == 1 ? 'Deactivate' : 'Activate' ?>
                                    </a>

                                    <?php if ((int) session()->get('user_id') !== (int) $user['id']): ?>

                                        <a
                                            href="<?= site_url('users/delete/' . $user['id']) ?>"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this user?');">
                                            Delete
                                        </a>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6" class="text-center">
                                No users found.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>