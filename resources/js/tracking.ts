// Pixel Meta della registrazione (stesso pixel di sinistropro.it). Si carica solo con il consenso ai cookie:
// il consenso sta nel cookie "sp-consenso" su .sinistropro.it, quindi chi ha già scelto sul sito non rivede il banner.
// Gli eventi importanti partono anche dal server (Conversions API) con lo stesso eventID: Meta li conta una volta.

type Fbq = ((...args: unknown[]) => void) & { callMethod?: (...a: unknown[]) => void; queue: unknown[]; loaded: boolean; version: string; push: unknown }

declare global {
  interface Window { fbq?: Fbq; _fbq?: Fbq }
}

export interface TrackEvent {
  event: string
  params?: Record<string, unknown>
  eventID?: string
}

const COOKIE = 'sp-consenso'
export const pixelId = document.querySelector<HTMLMetaElement>('meta[name="meta-pixel"]')?.content || ''

export function consent(): 'si' | 'no' | null {
  const c = document.cookie.split('; ').find((x) => x.startsWith(`${COOKIE}=`))?.split('=')[1]
  return c === 'si' || c === 'no' ? c : null
}

export function setConsent(value: 'si' | 'no') {
  const domain = location.hostname.endsWith('sinistropro.it') ? '; domain=.sinistropro.it' : ''
  document.cookie = `${COOKIE}=${value}; path=/; max-age=31536000; SameSite=Lax${domain}${location.protocol === 'https:' ? '; Secure' : ''}`
  if (value === 'si') pageView()
}

function loadPixel(): boolean {
  if (!pixelId || consent() !== 'si') return false
  if (window.fbq) return true

  const fbq = function (...args: unknown[]) {
    fbq.callMethod ? fbq.callMethod(...args) : fbq.queue.push(args)
  } as Fbq
  fbq.queue = []
  fbq.loaded = true
  fbq.version = '2.0'
  fbq.push = fbq
  window.fbq = window._fbq = fbq

  const script = document.createElement('script')
  script.async = true
  script.src = 'https://connect.facebook.net/it_IT/fbevents.js'
  document.head.appendChild(script)
  fbq('init', pixelId)
  return true
}

export function pageView() {
  if (loadPixel()) window.fbq!('track', 'PageView')
}

export function track({ event, params, eventID }: TrackEvent) {
  if (!loadPixel()) return
  // Un evento si conta una volta sola anche se la pagina viene ricaricata.
  if (eventID) {
    try {
      const key = `tracciato-${eventID}`
      if (localStorage.getItem(key)) return
      localStorage.setItem(key, '1')
    } catch { /* senza localStorage si manda comunque: il server ha lo stesso eventID */ }
  }
  window.fbq!('track', event, params ?? {}, eventID ? { eventID } : undefined)
}
