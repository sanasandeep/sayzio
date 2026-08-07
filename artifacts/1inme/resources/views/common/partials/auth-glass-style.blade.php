{{-- Liquid Glass card treatment for the auth surfaces (login, register,
     OTP, 2FA, verify-email, registration-paused). Consumes the shared
     liquid-glass tokens (--lg-*) from theme-styles, which flip per mode. --}}
<style>
    .auth-glass-card {
        border-radius: var(--lg-radius, 1.5rem);
        padding: 2rem 1.75rem;
        background: var(--lg-bg, rgba(255,255,255,0.045));
        border: 1px solid var(--lg-border, rgba(255,255,255,0.10));
        backdrop-filter: var(--lg-blur, blur(26px) saturate(1.4));
        -webkit-backdrop-filter: var(--lg-blur, blur(26px) saturate(1.4));
        box-shadow: var(--lg-shadow, 0 30px 70px -35px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.07));
    }
    @media (max-width: 480px) { .auth-glass-card { padding: 1.5rem 1.25rem; } }
</style>
