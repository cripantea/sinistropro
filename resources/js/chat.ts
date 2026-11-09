// Aiuti comuni alle chat Email e WhatsApp.

export function dimensione(bytes: number | null | undefined): string {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`
}

/** "Oggi", "Ieri" o la data estesa, per i separatori tra i giorni. */
export function giorno(iso: string): string {
  const d = new Date(iso)
  const oggi = new Date()
  const ieri = new Date()
  ieri.setDate(oggi.getDate() - 1)
  if (d.toDateString() === oggi.toDateString()) return 'Oggi'
  if (d.toDateString() === ieri.toDateString()) return 'Ieri'
  return d.toLocaleDateString('it-IT', {
    weekday: 'long', day: 'numeric', month: 'long',
    year: d.getFullYear() === oggi.getFullYear() ? undefined : 'numeric',
  })
}

/** true se il messaggio i-esimo apre un giorno nuovo. */
export function nuovoGiorno<T extends { createdAt: string }>(lista: T[], i: number): boolean {
  if (i === 0) return true
  return new Date(lista[i].createdAt).toDateString() !== new Date(lista[i - 1].createdAt).toDateString()
}

export function estensione(nome: string | null | undefined): string {
  const m = /\.([a-z0-9]{1,5})$/i.exec(nome ?? '')
  return m ? m[1].toUpperCase() : 'FILE'
}

export function ora(iso: string): string {
  return new Date(iso).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })
}
