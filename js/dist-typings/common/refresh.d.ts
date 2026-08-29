/**
 * For tests, and for a reader whose session is being replaced wholesale.
 */
export declare function resetRefresh(): void;
export declare function refreshTokens(now?: number): Promise<void>;
