import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            {...props}
            src="/images/branding/devolution-emblem.png"
            alt=""
            aria-hidden="true"
            draggable={false}
            className={`object-contain ${className ?? ''}`}
        />
    );
}
