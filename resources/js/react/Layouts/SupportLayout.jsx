import React from 'react';
import PortalPageHeader from '../Components/PortalPageHeader';

export default function SupportLayout({ children, showHeader = true }) {
    return (
        <div className="space-y-6">
            {showHeader ? <PortalPageHeader /> : null}
            {children}
        </div>
    );
}