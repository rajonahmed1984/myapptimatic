import React from 'react';

export default function PageHeader({ title = 'Overview', breadcrumb = [] }) {
    return (
        <div className="mb-4">
            <h1 className="text-lg font-semibold text-slate-900">{title}</h1>
            {Array.isArray(breadcrumb) && breadcrumb.length > 0 ? (
                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    {breadcrumb.map((item, index) => (
                        <React.Fragment key={`${item?.label || 'crumb'}-${index}`}>
                            {index > 0 ? <span className="text-slate-300">/</span> : null}
                            {item?.href ? (
                                <a href={item.href} data-native="true" className="hover:text-teal-600">
                                    {item.label}
                                </a>
                            ) : (
                                <span className="font-medium text-slate-600">{item?.label}</span>
                            )}
                        </React.Fragment>
                    ))}
                </div>
            ) : null}
        </div>
    );
}
