<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Task Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body class="bg-light">

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">

        <div class="col-md-5 col-lg-4">

            <div class="card shadow border-0">

                <div class="card-body p-4">

                    <h3 class="text-center mb-1">
                        Task Management
                    </h3>

                    <p class="text-center text-muted mb-4">
                        Login to your account
                    </p>


                    <!-- Success Message -->
                    <?php if (session()->getFlashdata('success')): ?>

                        <div class="alert alert-success">
                            <?= esc(session()->getFlashdata('success')) ?>
                        </div>

                    <?php endif; ?>


                    <!-- Error Message -->
                    <?php if (session()->getFlashdata('error')): ?>

                        <div class="alert alert-danger">
                            <?= esc(session()->getFlashdata('error')) ?>
                        </div>

                    <?php endif; ?>


                    <form method="post" action="<?= site_url('login') ?>">

                        <?= csrf_field() ?>


                        <!-- Email -->
                        <div class="mb-3">

                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?= esc(old('email')) ?>"
                                placeholder="Enter your email"
                                required
                                autofocus>

                        </div>


                        <!-- Password -->
                        <div class="mb-3">

                            <label class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                placeholder="Enter your password"
                                required>

                        </div>


                        <!-- Login -->
                        <button
                            type="submit"
                            class="btn btn-primary w-100">

                            Login

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>
</div>

</body>
</html>