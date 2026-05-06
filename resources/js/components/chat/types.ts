export type ChatMemberRole = 'admin' | 'member';

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

export type UnitChatThread = {
    id: number;
    name: string;
    createdLabel: string;
    avatarUrl?: string | null;
    statusLine: string;
    unreadCount: number;
    lastMessage: string;
    lastSeen: string;
    members: ChatMember[];
    messages: ChatMessage[];
};

export type ChatRoom = UnitChatThread;

export type EmployeeDirectoryItem = {
    id: number;
    name: string;
    employeeCode: string;
    avatarUrl?: string | null;
    statusLine: string;
};
