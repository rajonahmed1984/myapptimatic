import React from 'react';
import LegacyHtmlPage from '../../Shared/LegacyHtmlPage';

export default function HtmlPage(props) {
    return <LegacyHtmlPage fallbackTitle="Admin" {...props} />;
}
