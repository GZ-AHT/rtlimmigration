/** "nz" → 🇳🇿 (regional indicator letters). Empty for anything that isn't a two-letter code. */
export function flag(iso2: string | null | undefined): string {
    const code = (iso2 ?? '').toUpperCase();

    return /^[A-Z]{2}$/.test(code)
        ? String.fromCodePoint(
              ...code.split('').map((c) => 0x1f1a5 + c.charCodeAt(0)),
          )
        : '';
}
