/**
 * AuthModal — Sayzio sign-in modal for the browser.
 * Supports email/password login and email OTP.
 */
import { useState, useCallback } from 'react';
import { ApiClient, ApiClientError } from '../../shared/api-client';
import { useAuthStore } from '../store/auth-store';
import type { ApiUser } from '../../shared/api-client';

interface Props {
  onClose: () => void;
}

type Step = 'method' | 'password' | 'otp-send' | 'otp-verify' | '2fa';

/** Extracts the 2FA challenge token when the error is a `totp_required` gate. */
function extractChallengeToken(err: unknown): string | null {
  if (err instanceof ApiClientError && err.code === 'totp_required') {
    const details = err.details as { challenge_token?: unknown } | undefined;
    if (details && typeof details.challenge_token === 'string' && details.challenge_token) {
      return details.challenge_token;
    }
  }
  return null;
}

export function AuthModal({ onClose }: Props) {
  const { setAuth } = useAuthStore();
  const [step, setStep] = useState<Step>('method');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [otpCode, setOtpCode] = useState('');
  const [challengeToken, setChallengeToken] = useState<string | null>(null);
  const [twoFaCode, setTwoFaCode] = useState('');
  const [useRecoveryCode, setUseRecoveryCode] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const baseUrl = 'https://1in.me';

  const handlePasswordLogin = useCallback(async () => {
    if (!email.trim() || !password) return;
    setIsLoading(true);
    setError(null);
    try {
      const client = new ApiClient({ baseUrl });
      const result = await client.login(email.trim(), password, 'Zio Browser');
      await setAuth(result.user, result.token);
      onClose();
    } catch (err) {
      const token = extractChallengeToken(err);
      if (token) {
        setChallengeToken(token);
        setTwoFaCode('');
        setUseRecoveryCode(false);
        setStep('2fa');
        return;
      }
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setIsLoading(false);
    }
  }, [email, password, setAuth, onClose]);

  const handleSendOtp = useCallback(async () => {
    if (!email.trim()) return;
    setIsLoading(true);
    setError(null);
    try {
      const client = new ApiClient({ baseUrl });
      await client.sendOtp(email.trim(), 'email');
      setStep('otp-verify');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to send OTP');
    } finally {
      setIsLoading(false);
    }
  }, [email]);

  const handleVerifyOtp = useCallback(async () => {
    if (!otpCode.trim()) return;
    setIsLoading(true);
    setError(null);
    try {
      const client = new ApiClient({ baseUrl });
      const result = await client.verifyOtp(email.trim(), otpCode.trim(), 'email');
      await setAuth(result.user as ApiUser, result.token);
      onClose();
    } catch (err) {
      const token = extractChallengeToken(err);
      if (token) {
        setChallengeToken(token);
        setTwoFaCode('');
        setUseRecoveryCode(false);
        setStep('2fa');
        return;
      }
      setError(err instanceof Error ? err.message : 'Invalid code');
    } finally {
      setIsLoading(false);
    }
  }, [email, otpCode, setAuth, onClose]);

  const handleVerify2fa = useCallback(async () => {
    const code = twoFaCode.trim();
    if (!code || !challengeToken) return;
    setIsLoading(true);
    setError(null);
    try {
      const client = new ApiClient({ baseUrl });
      const result = useRecoveryCode
        ? await client.verifyBackupCode(challengeToken, code)
        : await client.verifyTotpChallenge(challengeToken, code);
      await setAuth(result.user, result.token);
      onClose();
    } catch (err) {
      if (err instanceof ApiClientError && err.status === 410) {
        // Challenge expired — restart the sign-in flow.
        setChallengeToken(null);
        setTwoFaCode('');
        setPassword('');
        setStep('method');
        setError('Your sign-in session expired. Please sign in again.');
        return;
      }
      setError(err instanceof Error ? err.message : 'Invalid code');
    } finally {
      setIsLoading(false);
    }
  }, [twoFaCode, challengeToken, useRecoveryCode, setAuth, onClose]);

  return (
    <div style={{
      position: 'fixed',
      inset: 0,
      background: 'rgba(0,0,0,0.7)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 1000,
    }}>
      <div style={{
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 16,
        padding: 32,
        width: 360,
        maxWidth: '90vw',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 24 }}>
          <div>
            <h2 style={{ fontSize: 20, fontWeight: 700 }}>Sign in to Sayzio</h2>
            <p style={{ fontSize: 13, color: 'var(--color-text-muted)', marginTop: 4 }}>
              Unlock Zio AI, contacts sync, and collections
            </p>
          </div>
          <button onClick={onClose} style={{ opacity: 0.6, fontSize: 18 }}>✕</button>
        </div>

        {error && (
          <div style={{
            padding: '8px 12px',
            borderRadius: 8,
            background: 'rgba(239,68,68,0.1)',
            border: '1px solid rgba(239,68,68,0.3)',
            color: 'var(--color-danger)',
            fontSize: 13,
            marginBottom: 16,
          }}>
            {error}
          </div>
        )}

        {step === 'method' && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <input
              value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="Email address"
              type="email"
              style={inputStyle}
              onKeyDown={e => e.key === 'Enter' && setStep('password')}
            />
            <div style={{ display: 'flex', gap: 10 }}>
              <button
                onClick={() => { if (email.trim()) setStep('password'); }}
                disabled={!email.trim()}
                style={{ ...btnStyle, flex: 1, opacity: !email.trim() ? 0.5 : 1 }}
              >Continue with password</button>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <div style={{ flex: 1, height: 1, background: 'var(--color-border)' }} />
              <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>or</span>
              <div style={{ flex: 1, height: 1, background: 'var(--color-border)' }} />
            </div>
            <button
              onClick={() => { if (email.trim()) void handleSendOtp(); }}
              disabled={!email.trim() || isLoading}
              style={{ ...btnSecondaryStyle, opacity: !email.trim() ? 0.5 : 1 }}
            >Sign in with Email OTP</button>
          </div>
        )}

        {step === 'password' && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ fontSize: 13, color: 'var(--color-text-muted)', marginBottom: 4 }}>
              Signing in as <strong>{email}</strong>
            </div>
            <input
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="Password"
              type="password"
              style={inputStyle}
              autoFocus
              onKeyDown={e => e.key === 'Enter' && void handlePasswordLogin()}
            />
            <button
              onClick={() => void handlePasswordLogin()}
              disabled={!password || isLoading}
              style={{ ...btnStyle, opacity: !password || isLoading ? 0.6 : 1 }}
            >{isLoading ? 'Signing in…' : 'Sign In'}</button>
            <button
              onClick={() => setStep('method')}
              style={{ ...btnSecondaryStyle }}
            >← Back</button>
          </div>
        )}

        {step === 'otp-verify' && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <p style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>
              We sent a code to <strong>{email}</strong>. Enter it below.
            </p>
            <input
              value={otpCode}
              onChange={e => setOtpCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder="6-digit code"
              type="text"
              maxLength={6}
              style={{ ...inputStyle, letterSpacing: 8, textAlign: 'center', fontSize: 20 }}
              autoFocus
              onKeyDown={e => e.key === 'Enter' && void handleVerifyOtp()}
            />
            <button
              onClick={() => void handleVerifyOtp()}
              disabled={otpCode.length < 4 || isLoading}
              style={{ ...btnStyle, opacity: otpCode.length < 4 || isLoading ? 0.6 : 1 }}
            >{isLoading ? 'Verifying…' : 'Verify'}</button>
            <button onClick={() => void handleSendOtp()} disabled={isLoading} style={btnSecondaryStyle}>
              Resend code
            </button>
          </div>
        )}

        {step === '2fa' && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <p style={{ fontSize: 13, color: 'var(--color-text-muted)' }}>
              {useRecoveryCode
                ? <>Enter one of your recovery codes for <strong>{email}</strong>.</>
                : <>Enter the 6-digit code from your authenticator app for <strong>{email}</strong>.</>}
            </p>
            <input
              value={twoFaCode}
              onChange={e => setTwoFaCode(
                useRecoveryCode
                  ? e.target.value.trim()
                  : e.target.value.replace(/\D/g, '').slice(0, 6),
              )}
              placeholder={useRecoveryCode ? 'Recovery code' : '6-digit code'}
              type="text"
              maxLength={useRecoveryCode ? 32 : 6}
              style={useRecoveryCode
                ? { ...inputStyle, textAlign: 'center' }
                : { ...inputStyle, letterSpacing: 8, textAlign: 'center', fontSize: 20 }}
              autoFocus
              onKeyDown={e => e.key === 'Enter' && void handleVerify2fa()}
            />
            <button
              onClick={() => void handleVerify2fa()}
              disabled={(useRecoveryCode ? !twoFaCode.trim() : twoFaCode.length < 6) || isLoading}
              style={{ ...btnStyle, opacity: (useRecoveryCode ? !twoFaCode.trim() : twoFaCode.length < 6) || isLoading ? 0.6 : 1 }}
            >{isLoading ? 'Verifying…' : 'Verify'}</button>
            <button
              onClick={() => {
                setUseRecoveryCode(v => !v);
                setTwoFaCode('');
                setError(null);
              }}
              disabled={isLoading}
              style={btnSecondaryStyle}
            >{useRecoveryCode ? 'Use authenticator code instead' : 'Use a recovery code instead'}</button>
            <button
              onClick={() => {
                setChallengeToken(null);
                setTwoFaCode('');
                setPassword('');
                setError(null);
                setStep('method');
              }}
              disabled={isLoading}
              style={btnSecondaryStyle}
            >← Back</button>
          </div>
        )}
      </div>
    </div>
  );
}

const inputStyle: React.CSSProperties = {
  width: '100%',
  height: 44,
  borderRadius: 10,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg)',
  color: 'var(--color-text)',
  padding: '0 14px',
  fontSize: 14,
  outline: 'none',
  fontFamily: 'inherit',
};

const btnStyle: React.CSSProperties = {
  width: '100%',
  height: 44,
  borderRadius: 10,
  background: 'var(--color-primary)',
  color: '#fff',
  fontSize: 14,
  fontWeight: 600,
  cursor: 'pointer',
  border: 'none',
};

const btnSecondaryStyle: React.CSSProperties = {
  width: '100%',
  height: 44,
  borderRadius: 10,
  background: 'var(--color-bg-elevated)',
  color: 'var(--color-text)',
  fontSize: 14,
  cursor: 'pointer',
  border: '1px solid var(--color-border)',
};
