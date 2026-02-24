import React from 'react';
import FormPage from './Form';

export default function Edit({
    pageTitle = 'Edit Employee',
    employee = {},
    managers = [],
    users = [],
    currencyOptions = ['BDT', 'USD'],
    documentLinks = {},
    routes = {},
}) {
    return (
        <FormPage
            mode="edit"
            pageTitle={pageTitle}
            heading="Edit employee"
            subheading="Update profile and employment details."
            submitLabel="Update employee"
            action={routes?.update}
            backUrl={routes?.index}
            method="PUT"
            managers={managers}
            users={users}
            currencyOptions={currencyOptions}
            values={employee}
            documentLinks={documentLinks}
        />
    );
}
