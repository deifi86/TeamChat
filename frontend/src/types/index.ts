// User Types
export interface User {
  id: number
  email: string
  username: string
  avatar_url: string
  status: UserStatus
  status_text: string | null
  created_at: string
}

export type UserStatus = 'available' | 'busy' | 'away' | 'offline'

// Company Types
export interface Company {
  id: number
  name: string
  slug: string
  logo_url: string | null
  owner: UserBasic
  members_count: number
  my_role: 'admin' | 'user'
  is_owner: boolean
  created_at: string
}

export interface CompanyMember extends UserBasic {
  role: 'admin' | 'user'
  joined_at: string
  is_owner: boolean
}

// Channel Types
export interface Channel {
  id: number
  company_id: number
  name: string
  description: string | null
  is_private: boolean
  is_member: boolean
  members_count: number
  has_pending_request?: boolean
  created_at?: string
}

export interface ChannelMember extends UserBasic {
  joined_at: string
}

export interface ChannelJoinRequest {
  id: number
  user: UserBasic
  message: string | null
  created_at: string
}

// Message Types
export interface Message {
  id: number
  content: string
  content_type: MessageContentType
  sender: UserBasic
  reactions: Reaction[]
  parent_id: number | null
  parent?: Message
  edited_at: string | null
  created_at: string
  file?: FileInfo
}

export type MessageContentType = 'text' | 'file' | 'image' | 'emoji'

export interface Reaction {
  emoji: string
  count: number
  user_ids: number[]
}

// Conversation Types
export interface Conversation {
  id: number
  other_user: UserBasic & { status: UserStatus }
  is_accepted: boolean
  is_pending_my_acceptance: boolean
  last_message: LastMessage | null
  unread_count: number
  updated_at: string
}

export interface LastMessage {
  content: string
  created_at: string
  is_mine: boolean
}

// File Types
export interface FileInfo {
  id: number
  original_name: string
  mime_type: string
  file_size: number
  human_size: string
  url: string
  thumbnail_url: string | null
  is_image: boolean
  is_compressed: boolean
  uploader?: UserBasic
  created_at: string
}

// Common Types
export interface UserBasic {
  id: number
  username: string
  avatar_url: string
  email?: string
  status?: UserStatus
}

export interface PaginationInfo {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}

// WebSocket Event Types
export interface NewMessageEvent {
  id: number
  content: string
  content_type: MessageContentType
  sender: UserBasic
  parent_id: number | null
  created_at: string
}

export interface MessageEditedEvent {
  id: number
  content: string
  edited_at: string
}

export interface MessageDeletedEvent {
  id: number
}

export interface UserTypingEvent {
  user: {
    id: number
    username: string
  }
  timestamp: string
}

export interface ReactionAddedEvent {
  message_id: number
  reaction: {
    id: number
    emoji: string
    user: UserBasic
  }
}

export interface ReactionRemovedEvent {
  message_id: number
  emoji: string
  user_id: number
}

export interface UserStatusChangedEvent {
  user_id: number
  status: UserStatus
  status_text: string | null
}
