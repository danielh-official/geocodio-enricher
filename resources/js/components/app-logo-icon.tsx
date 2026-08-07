import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M35.56 10.1A19 19 0 1 0 37.85 27.5L31.28 25.1A12 12 0 1 1 29.83 14.12L35.56 10.1ZM25 18.5H37.85V27.5L31.28 25.1H25V18.5Z"
            />
        </svg>
    );
}
