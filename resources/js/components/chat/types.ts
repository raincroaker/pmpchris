export type ChatMemberRole = 'owner' | 'admin' | 'member';

export type ChatMember = {
    id: number;
    name: string;
    employeeCode: string;
    avatarUrl?: string | null;
    role: ChatMemberRole;
    statusLine: string;
};

export type ChatMessage = {
    id: number;
    text: string;
    createdLabel: string;
    createdAtFullLabel?: string;
    role: 'me' | 'them';
    senderId?: number;
    senderLabel?: string;
    senderAvatarUrl?: string | null;
};

export type ChatMediaItem = {
    id: number;
    kind: 'image' | 'file' | 'link';
    title: string;
    subtitle: string;
    href?: string;
};

export type ChatRoom = {
    id: number;
    name: string;
    description: string;
    createdLabel: string;
    avatarUrl?: string | null;
    statusLine: string;
    unreadCount: number;
    lastMessage: string;
    lastSeen: string;
    isMuted: boolean;
    isPinned: boolean;
    members: ChatMember[];
    mediaItems: ChatMediaItem[];
    messages: ChatMessage[];
};

export type EmployeeDirectoryItem = {
    id: number;
    name: string;
    employeeCode: string;
    avatarUrl?: string | null;
    statusLine: string;
};
