import React from 'react';

export default function GuestAuthLayout({ children, wide = false }) {
    return (
        <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-6 md:py-8">
            <div className="flex flex-1 items-center justify-center">
                <div className={`w-[90%] max-w-[25.2rem] ${wide ? 'md:max-w-[45rem]' : ''}`}>
                    {children}
                </div>
            </div>

            <div className="mt-6 text-center text-xs text-slate-500">
                Copyright &copy; {new Date().getFullYear()}{' '}
                <a href="https://apptimatic.com" className="font-semibold text-teal-600 hover:text-teal-500">
                    Apptimatic
                </a>
                . All Rights Reserved.
            </div>
        </div>
    );
}
