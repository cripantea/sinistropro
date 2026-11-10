// Modelli pronti per partire in fretta: l'agenzia sceglie, l'editor si apre già compilato.

export interface DestinatarioAuto { type: string; user_id?: number }

export interface BozzaAutomazione {
  id?: number
  name: string
  trigger_type: string
  tenant_status_id: number | null
  watched_field: string | null
  days_before: number
  offset_days: number
  channel: 'email' | 'whatsapp' | 'both'
  subject: string | null
  recipients_to: DestinatarioAuto[]
  recipients_cc: DestinatarioAuto[]
  message_template: string
  is_active: boolean
  document_category_ids: number[]
}

export const BOZZA_VUOTA: BozzaAutomazione = {
  name: '',
  trigger_type: 'status',
  tenant_status_id: null,
  watched_field: null,
  days_before: 0,
  offset_days: -1,
  channel: 'email',
  subject: null,
  recipients_to: [{ type: 'cliente' }],
  recipients_cc: [],
  message_template: '',
  is_active: true,
  document_category_ids: [],
}

export interface Modello { chiave: string; titolo: string; descrizione: string; icona: string; soloClienti?: boolean; bozza: Partial<BozzaAutomazione> }

export const MODELLI: Modello[] = [
  {
    chiave: 'benvenuto',
    titolo: 'Benvenuto al nuovo sinistro',
    descrizione: 'Appena apri un sinistro, il cliente riceve conferma che ce ne stiamo occupando.',
    icona: '👋',
    bozza: {
      name: 'Benvenuto al cliente',
      trigger_type: 'pratica_created',
      subject: 'Abbiamo preso in carico il tuo sinistro',
      message_template: 'Gentile {nome_cliente},\nabbiamo aperto la pratica n. {numero_pratica} e ce ne stiamo occupando. Ti aggiorneremo a ogni passaggio.\n\n{nome_tenant}',
    },
  },
  {
    chiave: 'promemoria_perizia',
    titolo: 'Promemoria il giorno prima della perizia',
    descrizione: 'Il giorno prima del sopralluogo il cliente riceve data e ora.',
    icona: '📅',
    bozza: {
      name: 'Promemoria perizia',
      trigger_type: 'date_reminder',
      watched_field: 'data_appuntamento',
      offset_days: -1,
      channel: 'both',
      subject: 'Domani la perizia del tuo veicolo',
      message_template: 'Gentile {nome_cliente},\nti ricordiamo la perizia per il sinistro n. {numero_pratica} fissata per {data_perizia}.\nPer qualsiasi cambio rispondi a questo messaggio.\n\n{nome_tenant}',
    },
  },
  {
    chiave: 'perizia_fissata',
    titolo: 'Avviso perizia fissata',
    descrizione: 'Quando inserisci o cambi la data della perizia, cliente e perito vengono avvisati.',
    icona: '🔎',
    bozza: {
      name: 'Perizia fissata',
      trigger_type: 'date_field',
      watched_field: 'data_appuntamento',
      recipients_to: [{ type: 'cliente' }, { type: 'perito' }],
      subject: 'Perizia fissata — sinistro n. {numero_pratica}',
      message_template: 'Gentile {nome_cliente},\nla perizia per il sinistro n. {numero_pratica} è fissata per {data_perizia}.\n\n{nome_tenant}',
    },
  },
  {
    chiave: 'cambio_stato',
    titolo: 'Aggiornamento a ogni cambio di stato',
    descrizione: 'Il cliente sa sempre a che punto è la sua pratica.',
    icona: '🔄',
    bozza: {
      name: 'Aggiornamento stato al cliente',
      trigger_type: 'status',
      tenant_status_id: null,
      subject: 'Aggiornamento sul tuo sinistro n. {numero_pratica}',
      message_template: 'Gentile {nome_cliente},\nla tua pratica n. {numero_pratica} è passata allo stato: {stato_corrente}.\n\n{nome_tenant}',
    },
  },
  {
    chiave: 'scadenza_cliente',
    titolo: 'Scadenza di una data del cliente',
    descrizione: 'Es. 30 giorni prima della scadenza della polizza o della patente.',
    icona: '⏰',
    soloClienti: true,
    bozza: {
      name: 'Promemoria scadenza',
      trigger_type: 'cliente_date_field',
      days_before: 30,
      subject: 'Promemoria scadenza',
      message_template: 'Gentile {nome_cliente},\nti ricordiamo la scadenza del {data_scadenza}. Contattaci per rinnovare.\n\n{nome_tenant}',
    },
  },
]
