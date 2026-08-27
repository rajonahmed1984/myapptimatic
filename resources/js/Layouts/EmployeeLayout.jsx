import React from 'react';
import AdminLayout from './AdminLayout';

export default function EmployeeLayout({ children, ...props }) {
    return <AdminLayout {...props}>{children}</AdminLayout>;
}