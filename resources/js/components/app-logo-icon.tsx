import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 64 64"
            role="img"
            aria-label="IDMIS"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <g
                stroke="currentColor"
                strokeWidth="3.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <path d="M13 12v40M13 12h8c13 0 20 7 20 20s-7 20-20 20h-8" />
                <path d="M13 32h19M32 32h19" />
                <path
                    d="M32 32l9-13 10 13 8-13v33"
                    className="stroke-[#147a55] dark:stroke-[#78c7a4]"
                />
            </g>
            <g fill="currentColor">
                <circle cx="13" cy="12" r="4" />
                <circle cx="13" cy="32" r="4" />
                <circle cx="13" cy="52" r="4" />
                <circle cx="32" cy="32" r="4" />
            </g>
            <g className="fill-[#147a55] dark:fill-[#78c7a4]">
                <circle cx="41" cy="19" r="4" />
                <circle cx="51" cy="32" r="4" />
                <circle cx="59" cy="19" r="4" />
                <circle cx="59" cy="52" r="4" />
            </g>
            <circle cx="22" cy="32" r="4" className="fill-[#c8902f]" />
        </svg>
    );
}
