import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            {/* ボードのヘッダーバー */}
            <rect x="1" y="1" width="38" height="7" rx="2" />
            {/* 列1（Todo）: カード3枚 */}
            <rect x="1" y="11" width="10" height="6" rx="1.5" />
            <rect x="1" y="20" width="10" height="6" rx="1.5" />
            <rect x="1" y="29" width="10" height="6" rx="1.5" />
            {/* 列2（In Progress）: カード2枚 */}
            <rect x="15" y="11" width="10" height="6" rx="1.5" />
            <rect x="15" y="20" width="10" height="6" rx="1.5" />
            {/* 列3（Done）: カード1枚 */}
            <rect x="29" y="11" width="10" height="6" rx="1.5" />
        </svg>
    );
}
