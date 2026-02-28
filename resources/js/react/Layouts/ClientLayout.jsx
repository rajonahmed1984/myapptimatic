import React from 'react';
import PortalPageHeader from '../Components/PortalPageHeader';

export default function ClientLayout({ children, showHeader = true }) {
    return (
        <div className="space-y-6">
            {showHeader ? <PortalPageHeader /> : null}
            {children}
        </div>
    );
}