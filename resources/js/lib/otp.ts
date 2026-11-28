/**
 * Authenticator apps and emails often show a code as "123 456". Without this the
 * space is pasted too and the last digit is cut off, so the code is rejected.
 */
export function digitsOnly(pasted = ''): string {
    return pasted.replace(/\D/g, '');
}
