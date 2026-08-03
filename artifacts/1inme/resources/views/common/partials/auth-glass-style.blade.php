{{-- Liquid Glass card treatment for the auth surfaces (login, register,
     OTP, 2FA, verify-email, registration-paused). Relies on the shared
     theme tokens from theme-styles being present; pairs dark + light. --}}
<style>
    .auth-glass-card {
        border-radius: 1.5rem;
        padding: 2rem 1.75rem;
        background: rgba(255,255,255,0.045);
        border: 1px solid rgba(255,255,255,0.10);
        backdrop-filter: blur(26px) saturate(1.4);
        -webkit-backdrop-filter: blur(26px) saturate(1.4);
        box-shadow: 0 30px 70px -35px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.07);
    }
    html.light-mode .auth-glass-card {
        background: rgba(255,255,255,0.62);
        border-color: rgba(15,23,42,0.09);
        box-shadow: 0 30px 70px -45px rgba(15,23,42,0.35), inset 0 1px 0 rgba(255,255,255,0.85);
    }
    @media (max-width: 480px) { .auth-glass-card { padding: 1.5rem 1.25rem; } }
</style>
