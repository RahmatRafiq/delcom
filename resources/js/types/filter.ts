// =====================================================
// Filter Types (Legacy - kept for constants only)
// =====================================================

export type FilterType = 'keyword' | 'phrase' | 'regex' | 'username' | 'url' | 'emoji_spam' | 'repeat_char';
export type FilterMatchType = 'exact' | 'contains' | 'starts_with' | 'ends_with' | 'regex';
export type FilterAction = 'delete' | 'hide' | 'flag' | 'report';
