import { useCallback } from 'react';

export type CleanupFn = () => void;

export function useMobileNavigation(): CleanupFn {
    return useCallback(() => {
        // ドロワー終了時に body の pointer-events スタイルを除去する。
        document.body.style.removeProperty('pointer-events');
    }, []);
}
