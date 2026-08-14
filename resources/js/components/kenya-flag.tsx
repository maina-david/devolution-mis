import type { SVGAttributes } from 'react';
import { useCommonCopy } from '@/hooks/use-localization';

export default function KenyaFlag(props: SVGAttributes<SVGElement>) {
    const copy = useCommonCopy();

    return (
        <svg
            {...props}
            viewBox="0 0 90 60"
            role="img"
            aria-label={copy.flag_of_kenya}
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect width="90" height="60" rx="2" fill="#006600" />
            <rect width="90" height="40" rx="2" fill="#BB0000" />
            <rect width="90" height="20" rx="2" fill="#000000" />
            <rect y="18" width="90" height="4" fill="#FFFFFF" />
            <rect y="38" width="90" height="4" fill="#FFFFFF" />
            <g transform="translate(45 30)">
                <path
                    d="M-15-20 12 20M15-20-12 20"
                    stroke="#FFFFFF"
                    strokeWidth="2.4"
                    strokeLinecap="round"
                />
                <path
                    d="M-14-19 13 21M14-19-13 21"
                    stroke="#000000"
                    strokeWidth="1"
                    strokeLinecap="round"
                />
                <path
                    d="M0-20C11-13 12 12 0 20-12 12-11-13 0-20Z"
                    fill="#BB0000"
                    stroke="#FFFFFF"
                    strokeWidth="2"
                />
                <path
                    d="M0-18v36M-7-13 7 13M7-13-7 13"
                    stroke="#000000"
                    strokeWidth="3"
                />
                <path d="M0-18v36" stroke="#FFFFFF" strokeWidth="1.2" />
            </g>
        </svg>
    );
}
