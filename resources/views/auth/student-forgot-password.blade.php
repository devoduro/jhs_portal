<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Transcript System') }} - Reset Password</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .login-container { max-width: 450px; margin: 0 auto; padding: 2rem; }
        .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); }
        .card-header {
            background: linear-gradient(to right, #37a23fff, #00b300ff);
            color: white; text-align: center; padding: 1.5rem; border-bottom: none;
        }
        .btn-primary { background: linear-gradient(to right, #088404ff, #118308ff); border: none; }
        .btn-primary:hover { background: linear-gradient(to right, #066303ff, #0e6906ff); }
        .form-control:focus { border-color: #007bff; box-shadow: 0 0 0 0.25rem rgba(0,123,255,.25); }
        .input-group-text { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 login-container">
                <div class="card">
                    <div class="card-header">
                        @php($institutionLogo = \App\Models\Setting::get('institution_logo'))
                        <img src="{{ $institutionLogo ? asset('storage/' . $institutionLogo) : asset('images/logos/logo.png') }}" alt="{{ \App\Models\Setting::get('institution_name', 'Your School Name') }}" class="mb-3" style="height: 80px;">
                        <h3 class="mb-0">Reset Password</h3>
                        <p class="mb-0">Enter your registered phone number</p>
                    </div>
                    <div class="card-body p-4">

                        @if (session('status'))
                            <div class="alert alert-success mb-3">{{ session('status') }}</div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger mb-3">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <p class="text-muted small mb-3">
                            Choose how you'd like to receive your password reset link.
                        </p>

                        <ul class="nav nav-pills nav-fill mb-4" id="reset-channel-tabs">
                            <li class="nav-item">
                                <button type="button" class="nav-link active" data-channel="sms" onclick="showChannel('sms')">
                                    <i class="fas fa-sms me-1"></i> SMS
                                </button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" data-channel="email" onclick="showChannel('email')">
                                    <i class="fas fa-envelope me-1"></i> Email
                                </button>
                            </li>
                        </ul>

                        <div id="sms-channel">
                            <form method="POST" action="{{ route('student.password.email') }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                               id="phone" name="phone" value="{{ old('phone') }}"
                                               placeholder="e.g. 0241234567" required>
                                    </div>
                                    @error('phone')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="d-grid gap-2 mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link via SMS
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div id="email-channel" style="display: none;">
                            <form method="POST" action="{{ route('student.password.email-request') }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                                               id="email" name="email" value="{{ old('email') }}"
                                               placeholder="you@example.com">
                                    </div>
                                    @error('email')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <p class="text-muted small mt-1 mb-0">Must match the email on file with your school.</p>
                                </div>
                                <div class="d-grid gap-2 mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link via Email
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('student.login') }}" class="text-decoration-none text-muted small">
                                <i class="fas fa-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>

                        <script>
                            function showChannel(channel) {
                                document.getElementById('sms-channel').style.display = channel === 'sms' ? '' : 'none';
                                document.getElementById('email-channel').style.display = channel === 'email' ? '' : 'none';
                                document.querySelectorAll('#reset-channel-tabs .nav-link').forEach(function (btn) {
                                    btn.classList.toggle('active', btn.dataset.channel === channel);
                                });
                            }
                            @if($errors->has('email'))
                                showChannel('email');
                            @endif
                        </script>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
