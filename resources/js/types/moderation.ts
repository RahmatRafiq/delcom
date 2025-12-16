import type { Filter } from './filter';
import type { UserPlatform } from './platform';

// =====================================================
// Moderation Types
// =====================================================

export type ModerationActionTaken = 'deleted' | 'hidden' | 'flagged' | 'reported' | 'failed';
export type ModerationActionSource = 'background_job' | 'extension' | 'manual';

export interface ModerationLog {
    id: number;
    user_id: number;
    user_platform_id: number;
    platform_comment_id: string;
    video_id: string | null;
    post_id: string | null;
    commenter_username: string | null;
    commenter_id: string | null;
    comment_text: string | null;
    matched_filter_id: number | null;
    matched_pattern: string | null;
    action_taken: ModerationActionTaken;
    action_source: ModerationActionSource;
    failure_reason: string | null;
    processed_at: string;
    created_at: string;
    updated_at: string;
    user_platform?: UserPlatform;
    matched_filter?: Filter;
}
