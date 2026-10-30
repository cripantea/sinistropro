import { router } from '@inertiajs/vue3'

// "Indietro" dal sinistro: si torna da dove lo si è aperto (tabella con i suoi filtri,
// Board, scheda cliente, registro email) e non sempre alla tabella.
const CHIAVE = 'sinistro-ritorno'

const ORIGINI = [
  /^\/pratiche(\?|$)/,
  /^\/pratiche\/kanban(\?|$)/,
  /^\/clienti\/\d+(\?|$)/,
  /^\/email-log(\?|$)/,
  /^\/dashboard(\?|$)/,
]

// Pagine "dentro" il sinistro (scheda, modifica, chat, modifica del suo cliente): non cambiano l'origine.
const DENTRO = [
  /^\/pratiche\/\d+(\/|\?|$)/,
  /^\/clienti\/\d+\/edit/,
]

function leggi(): string | null {
  try { return sessionStorage.getItem(CHIAVE) } catch { return null }
}

function scrivi(url: string | null) {
  try {
    if (url) sessionStorage.setItem(CHIAVE, url)
    else sessionStorage.removeItem(CHIAVE)
  } catch { /* storage non disponibile: si torna alla tabella */ }
}

export function registraRitornoSinistro() {
  router.on('navigate', (event) => {
    const url = event.detail.page.url
    if (ORIGINI.some(r => r.test(url))) scrivi(url)
    else if (!DENTRO.some(r => r.test(url))) scrivi(null)
  })
}

export function ritornoSinistro(): { href: string; etichetta: string; daBoard: boolean } {
  const url = leggi()
  if (url?.startsWith('/pratiche/kanban')) return { href: url, etichetta: 'Board', daBoard: true }
  if (url?.startsWith('/clienti/')) return { href: url, etichetta: 'Cliente', daBoard: false }
  if (url?.startsWith('/email-log')) return { href: url, etichetta: 'Registro email', daBoard: false }
  if (url?.startsWith('/dashboard')) return { href: url, etichetta: 'Dashboard', daBoard: false }
  return { href: url ?? route('pratiche.index'), etichetta: 'Sinistri', daBoard: false }
}
