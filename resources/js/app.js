// SOW §3.13 — WebAuthn helpers used by the passkey components
// (spatie/laravel-passkeys expects these as globals).
import { startRegistration, startAuthentication } from '@simplewebauthn/browser';

window.startRegistration = startRegistration;
window.startAuthentication = startAuthentication;
