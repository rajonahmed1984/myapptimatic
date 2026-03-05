import React from 'react';
import FormPage from './Form';

export default function Create({
    pageTitle = 'Add Employee',
    managers = [],
    users = [],
    currencyOptions = ['BDT', 'USD'],
    defaults = {},
    routes = {},
}) {
    return (
        <FormPage
            mode="create"
            pageTitle={pageTitle}
            heading="Add employee"
            subheading="Create a new employee record."
            submitLabel="Create employee"
            action={routes?.store}
            backUrl={routes?.index}
            method="POST"
            managers={managers}
            users={users}
            currencyOptions={currencyOptions}
            values={defaults}
        />
    );
}
