import { AxiosInstance } from 'axios'

declare global {
  interface Window {
    axios: AxiosInstance
  }
}

export interface User {
  id: number
  name: string
  email: string
  role: string
  tenant_id: number | null
  email_verified_at?: string
}

export interface AppNotification {
  id: number
  user_name: string
  action: string
  model: string
  created_at: string
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User
    isImpersonating: boolean
  }
  flash: {
    success?: string
    error?: string
    credenziali?: string
  }
  notifications: AppNotification[]
  tenantFeatures: Record<string, boolean>
  impersonating: { tenant_id: number; tenant_name: string } | null
  tenantContext: { tenant_id: number; tenant_name: string } | null
  abbonamento?: { state: BillingState; has_access: boolean; free_until: string | null } | null
}

export type BillingState = 'esterno' | 'gratis' | 'attivo' | 'disdetto' | 'in_ritardo' | 'non_attivo'

export interface BillingSummary {
  state: BillingState
  has_access: boolean
  has_subscription: boolean
  interval: 'month' | 'year' | null
  ends_at: string | null
  cancel_at_period_end: boolean
  free_until: string | null
  price_monthly_cents: number
  price_yearly_cents: number
  setup_fee_cents: number
}
