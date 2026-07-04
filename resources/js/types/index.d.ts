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
  }
  notifications: AppNotification[]
}
