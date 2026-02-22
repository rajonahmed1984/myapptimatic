# Health Remediation Report

Generated: 2026-02-22 21:19:02 +06:00

## PHASE 0 — Baseline Snapshot


### System Info


$ System.Collections.Hashtable | Out-String

powershell.exe : System.Collections.Hashtable : The term 'System.Collections.Hashtable' is not recognized as the name 
of a cmdlet, 
At line:11 char:13
+   $output = & powershell -NoProfile -Command $cmd 2>&1 | Out-String
+             ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    + CategoryInfo          : NotSpecified: (System.Collecti...e of a cmdlet, :String) [], RemoteException
    + FullyQualifiedErrorId : NativeCommandError
 
function, script file, or operable program. Check the spelling of the name, or if a path was included, verify that the 
path is correct and try again.
At line:1 char:1
+ System.Collections.Hashtable | Out-String
+ ~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    + CategoryInfo          : ObjectNotFound: (System.Collections.Hashtable:String) [], CommandNotFoundException
    + FullyQualifiedErrorId : CommandNotFoundException


$ [System.Environment]::OSVersion.VersionString

Microsoft Windows NT 10.0.26200.0


$ php -v

PHP 8.2.12 (cli) (built: Oct 24 2023 21:15:15) (ZTS Visual C++ 2019 x64)
Copyright (c) The PHP Group
Zend Engine v4.2.12, Copyright (c) Zend Technologies


$ node -v; npm -v

v22.19.0
10.9.3


### Repository Status


$ git status --short

 M app/Console/Commands/GenerateRecurringExpenses.php
 M app/Console/Commands/ScanDataIntegrity.php
 M app/Http/Controllers/Admin/RecurringExpenseController.php
 M app/Http/Controllers/ProjectTaskSubtaskController.php
 M app/Http/Controllers/SupportTicketAttachmentController.php
 M app/Models/ProjectTaskSubtask.php
 M app/Models/RecurringExpense.php
 M app/Services/RecurringExpenseGenerator.php
 M resources/views/admin/expenses/recurring/index.blade.php
 M resources/views/projects/task-detail-clickup.blade.php
 M routes/web.php
?? app/Models/RecurringExpenseAdvance.php
?? database/migrations/2026_04_16_000800_add_attachment_path_to_project_task_subtasks_table.php
?? database/migrations/2026_04_20_000100_create_recurring_expense_advances_table.php
?? docs/health_remediation_report.md
?? junit-full.xml
?? junit.xml


$ composer --version

Composer version 2.8.11 2025-08-21 11:29:39
powershell.exe : PHP version 8.2.12 (C:\xampp\php\php.exe)
At line:11 char:13
+   $output = & powershell -NoProfile -Command $cmd 2>&1 | Out-String
+             ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    + CategoryInfo          : NotSpecified: (PHP version 8.2...pp\php\php.exe):String) [], RemoteException
    + FullyQualifiedErrorId : NativeCommandError
 
Run the "diagnose" command to get more detailed diagnostics output.


### Security Audits


$ composer audit --locked

powershell.exe : Found 3 security vulnerability advisories affecting 3 packages:
At line:11 char:13
+   $output = & powershell -NoProfile -Command $cmd 2>&1 | Out-String
+             ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    + CategoryInfo          : NotSpecified: (Found 3 securit...ing 3 packages::String) [], RemoteException
    + FullyQualifiedErrorId : NativeCommandError
 
+-------------------+----------------------------------------------------------------------------------+
| Package           | phpunit/phpunit                                                                  |
| Severity          | high                                                                             |
| CVE               | CVE-2026-24765                                                                   |
| Title             | PHPUnit Vulnerable to Unsafe Deserialization in PHPT Code Coverage Handling      |
| URL               | https://github.com/advisories/GHSA-vvj3-c3rp-c85p                                |
| Affected versions | >=12.0.0,<12.5.8|>=11.0.0,<11.5.50|>=10.0.0,<10.5.62|>=9.0.0,<9.6.33|<8.5.52     |
| Reported at       | 2026-01-27T22:26:22+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | psy/psysh                                                                        |
| Severity          | medium                                                                           |
| CVE               | CVE-2026-25129                                                                   |
| Title             | PsySH has Local Privilege Escalation via CWD .psysh.php auto-load                |
| URL               | https://github.com/advisories/GHSA-4486-gxhx-5mg7                                |
| Affected versions | <=0.11.22|>=0.12.0,<=0.12.18                                                     |
| Reported at       | 2026-01-30T21:28:44+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | symfony/process                                                                  |
| Severity          | medium                                                                           |
| CVE               | CVE-2026-24739                                                                   |
| Title             | Symfony's incorrect argument escaping under MSYS2/Git Bash can lead to           |
|                   | destructive file operations on Windows                                           |
| URL               | https://github.com/advisories/GHSA-r39x-jcww-82v6                                |
| Affected versions | >=8.0,<8.0.5|>=7.4,<7.4.5|>=7.3,<7.3.11|>=6.4,<6.4.33|<5.4.51                    |
| Reported at       | 2026-01-28T21:28:10+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+


$ npm audit --audit-level=moderate

# npm audit report

esbuild  <=0.24.2
Severity: moderate
esbuild enables any website to send any requests to the development server and read the response - https://github.com/advisories/GHSA-67mh-4wv8-2f99
fix available via `npm audit fix --force`
Will install vite@7.3.1, which is a breaking change
node_modules/esbuild
  vite  0.11.0 - 6.1.6
  Depends on vulnerable versions of esbuild
  node_modules/vite

2 moderate severity vulnerabilities

To address all issues (including breaking changes), run:
  npm audit fix --force


### Application Health


$ php artisan about


  Environment ........................................................................................................  
  Application Name ........................................................................................ Apptimatic  
  Laravel Version ............................................................................................ 11.47.0  
  PHP Version ................................................................................................. 8.2.12  
  Composer Version ............................................................................................ 2.8.11  
  Environment .................................................................................................. local  
  Debug Mode ................................................................................................. ENABLED  
  URL ................................................................................................. 127.0.0.1:8000  
  Maintenance Mode ............................................................................................... OFF  
  Timezone ................................................................................................ Asia/Dhaka  
  Locale .......................................................................................................... en  

  Cache ..............................................................................................................  
  Config .................................................................................................. NOT CACHED  
  Events .................................................................................................. NOT CACHED  
  Routes .................................................................................................. NOT CACHED  
  Views ....................................................................................................... CACHED  

  Drivers ............................................................................................................  
  Broadcasting ................................................................................................... log  
  Cache ..................................................................................................... database  
  Database ..................................................................................................... mysql  
  Logs ................................................................................................ stack / single  
  Mail ...................................................................................................... sendmail  
  Queue ..................................................................................................... database  
  Session ................................................................................................... database


$ vendor/bin/phpunit

PHPUnit 10.5.60 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.12
Configuration: C:\xampp\htdocs\myapptimatic\phpunit.xml

...??............................................................  63 / 275 ( 22%)
...F.........................................F........F........ 126 / 275 ( 45%)
............................................................... 189 / 275 ( 68%)
............................................................... 252 / 275 ( 91%)
F.....................F                                         275 / 275 (100%)

Time: 00:34.905, Memory: 114.00 MB

There were 5 failures:

1) Tests\Feature\ClientInvoiceAccessTest::client_can_view_own_invoice
Failed asserting that '<!DOCTYPE html>\n
<html lang="en">\n
<head>\n
    <meta charset="utf-8">\n
<meta name="viewport" content="width=device-width, initial-scale=1">\n
<meta name="csrf-token" content="pfb3giXMnek1piIrnHle03fVHrCSg8n4chNCdrd7">\n
<title>Invoice</title>\n
<link rel="preconnect" href="https://fonts.googleapis.com">\n
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">\n
<script src="https://cdn.tailwindcss.com"></script>\n
<link rel="preload" as="style" href="http://127.0.0.1:8000/build/assets/app-CD_PM2Md.css" /><link rel="modulepreload" href="http://127.0.0.1:8000/build/assets/app-Nj5TQMYn.js" /><link rel="stylesheet" href="http://127.0.0.1:8000/build/assets/app-CD_PM2Md.css" /><script type="module" src="http://127.0.0.1:8000/build/assets/app-Nj5TQMYn.js"></script>    <style>\n
        * { box-sizing: border-box; }\n
        .invoice-container { width: 100%; background: #fff; padding: 10px; color: #333; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }\n
        .invoice-container .row { display: flex; flex-wrap: wrap; margin: 0 -15px; }\n
        .invoice-container .invoice-grid { display: table; width: 100%; table-layout: fixed; }\n
        .invoice-container .invoice-grid > .invoice-col { display: table-cell; width: 50%; vertical-align: top; }\n
        .invoice-container .invoice-col { width: 50%; padding: 0 15px; }\n
        .invoice-container .invoice-col.full { width: 100%; }\n
        .invoice-container .invoice-col.right { text-align: right; }\n
        .invoice-container .logo-wrap { display: flex; align-items: center; }\n
        .invoice-container .invoice-logo { width: 300px; max-width: 100%; height: auto; }\n
        .invoice-container .invoice-logo-fallback { font-size: 54px; font-weight: 800; color: #211f75; letter-spacing: -1px; line-height: 1; }\n
        .invoice-container .invoice-status { margin: 20px 0 0; font-size: 24px; font-weight: bold; }\n
        .invoice-container .invoice-status h3 { margin: 0; font-size: 18px; font-weight: 600; }\n
        .invoice-container .small-text { font-size: 0.92em; }\n
        .invoice-container hr { margin: 20px 0; border: 0; border-top: 1px solid #eee; }\n
        .invoice-container address { margin: 8px 0 0; font-style: normal; line-height: 1.5; }\n
        .invoice-container .panel { margin-top: 14px; background: #fff; }\n
        .invoice-container .panel-heading { padding: 0 0 8px; background: transparent; border: 0; }\n
        .invoice-container .panel-title { margin: 0; font-size: 16px; }\n
        .invoice-container .table-responsive { width: 100%; overflow-x: auto; }\n
        .invoice-container .table { width: 100%; max-width: 100%; margin-bottom: 20px; border-collapse: collapse; }\n
        .invoice-container .table > thead > tr > td,\n
        .invoice-container .table > tbody > tr > td { padding: 8px; line-height: 1.42857143; vertical-align: top; border: 1px solid #ddd; }\n
        .invoice-container .text-right { text-align: right !important; }\n
        .invoice-container .text-center { text-align: center !important; }\n
        .invoice-container .mt-5 { margin-top: 50px; }\n
        .invoice-container .mb-3 { margin-bottom: 30px; }\n
        .invoice-container .unpaid, .invoice-container .overdue { color: #cc0000; }\n
        .invoice-container .paid { color: #779500; }\n
        .invoice-container .refunded { color: #224488; }\n
        .invoice-container .cancelled { color: #888; }\n
        .invoice-container .text-muted { color: #666; }\n
        .payment-panel { border: 1px solid #ddd; padding: 12px; margin-top: 18px; }\n
        .payment-heading { font-weight: 700; margin-bottom: 8px; }\n
        .gateway-form .form-control { width: 100%; border: 1px solid #ccc; padding: 8px; margin-top: 6px; }\n
        .btn-primary { margin-top: 10px; border: 1px solid #0f766e; background: #0f766e; color: #fff; padding: 8px 14px; border-radius: 3px; cursor: pointer; }\n
        .btn-default { border: 1px solid #ccc; background: #fff; color: #333; padding: 6px 12px; text-decoration: none; display: inline-block; }\n
        .btn-group .btn-default + .btn-default { margin-left: -1px; }\n
        .alert { padding: 8px 10px; margin-bottom: 10px; border: 1px solid transparent; }\n
        .alert.amber { border-color: #fcd34d; background: #fffbeb; color: #92400e; }\n
        .alert.rose { border-color: #fecdd3; background: #fff1f2; color: #9f1239; }\n
        @media (max-width: 767px) {\n
            .invoice-container .invoice-col { padding: 0 10px; }\n
            .invoice-container .invoice-logo { width: 220px; }\n
        }\n
        @media print {\n
            .invoice-container .invoice-grid { display: table !important; width: 100% !important; table-layout: fixed !important; }\n
            .invoice-container .invoice-grid > .invoice-col { display: table-cell !important; width: 50% !important; vertical-align: top !important; }\n
            .no-print, .no-print * { display: none !important; }\n
        }\n
    </style>\n
</head>\n
<body class="bg-dashboard">\n
    <div class="min-h-screen flex flex-col md:flex-row">\n
        <div id="clientSidebarOverlay" class="fixed inset-0 z-20 bg-slate-900/60 opacity-0 pointer-events-none transition-opacity duration-200 md:hidden"></div>\n
        <aside id="clientSidebar" class="sidebar fixed inset-y-0 left-0 z-30 flex w-72 max-w-[90vw] flex-shrink-0 flex-col px-6 py-7 overflow-y-auto max-h-screen transform transition-transform duration-200 ease-in-out -translate-x-full md:w-64 md:max-w-none md:translate-x-0 md:overflow-y-auto md:max-h-screen md:sticky md:top-0">\n
            <div class="flex items-center gap-3">\n
                                                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">CL</div>\n
                                <div>\n
                    <div class="text-xs uppercase tracking-[0.35em] text-slate-400">Client</div>\n
                    <div class="text-lg font-semibold text-white">Apptimatic</div>\n
                </div>\n
            </div>\n
            <button type="button" id="clientSidebarClose" class="absolute right-4 top-4 rounded-full border border-white/10 bg-white/10 p-2 text-slate-200 transition hover:bg-white/20 md:hidden" aria-label="Close menu">\n
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">\n
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />\n
                </svg>\n
            </button>\n
\n
            <nav class="mt-10 space-y-4 text-sm">\n
                                \n
                <div>\n
                    <a class="nav-link" href="http://127.0.0.1:8000/client/dashboard">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                                                    Overview\n
                                            </a>\n
                </div>\n
                \n
                                <div class="space-y-2">\n
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Projects & Services</div>\n
                    <a class="nav-link" href="http://127.0.0.1:8000/client/projects">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        Projects\n
                    </a>\n
                                            <a class="nav-link" href="http://127.0.0.1:8000/client/tasks">\n
                            <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            <span>Tasks</span>\n
                            <span class="ml-auto rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">0</span>\n
                        </a>\n
                                        <a class="nav-link" href="http://127.0.0.1:8000/client/chats">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        <span>Chat</span>\n
                        <span class="ml-auto rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">0</span>\n
                    </a>\n
                    <a class="nav-link" href="http://127.0.0.1:8000/client/services">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        Services\n
                    </a>\n
                    <a class="nav-link" href="http://127.0.0.1:8000/client/domains">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        Domains\n
                    </a>\n
                    <a class="nav-link" href="http://127.0.0.1:8000/client/licenses">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        Licenses\n
                    </a>\n
                </div>\n
\n
                <div class="space-y-2">\n
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Orders & Requests</div>\n
                    <a class="nav-link" href="http://127.0.0.1:8000/client/orders">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        My Orders\n
                    </a>\n
                </div>\n
\n
                <div class="space-y-2">\n
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Billing & Payments</div>\n
                    <a class="nav-link nav-link-active" href="http://127.0.0.1:8000/client/invoices">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        Invoices\n
                    </a>\n
                </div>\n
\n
                <div class="space-y-2">\n
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Support & Growth</div>\n
                    <a class="nav-link" href="http://127.0.0.1:8000/client/support-tickets">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        Support\n
                                            </a>\n
                    <a class="nav-link" href="http://127.0.0.1:8000/client/affiliates">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        Affiliates\n
                    </a>\n
                </div>\n
                            </nav>\n
\n
            <div class="mt-auto">              \n
                <div class="mt-5 space-y-2">\n
                    <a href="http://127.0.0.1:8000/client/profile" class="nav-link">\n
                        <span class="h-2 w-2 rounded-full bg-current"></span>\n
                        Profile Settings\n
                    </a>\n
                </div>\n
                <div class="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200">\n
                    <div class="flex items-center gap-3">\n
                        <div class="h-10 w-10 overflow-hidden rounded-full border border-white/20">\n
                            <div class="h-10 w-10 rounded-full overflow-hidden flex items-center justify-center bg-slate-100 text-slate-600 text-xs font-semibold">\n
            <span>CV</span>\n
    </div>\n
                        </div>\n
                        <div class="min-w-0">\n
                            <div class="text-sm font-semibold text-white">Conor Volkman</div>\n
                            <div class="text-xs text-slate-300">Client</div>                            \n
                        </div>\n
                    </div>                    \n
                    <form method="POST" action="http://127.0.0.1:8000/logout" class="mt-3">\n
                        <input type="hidden" name="_token" value="pfb3giXMnek1piIrnHle03fVHrCSg8n4chNCdrd7" autocomplete="off">                        <button type="submit" class="w-full rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/20">\n
                            Sign out\n
                        </button>\n
                    </form>                    \n
                </div>\n
            </div>\n
        </aside>\n
\n
        <div class="flex-1 flex flex-col w-full min-w-0">\n
            <header class="sticky top-0 z-20 border-b border-slate-300/70 bg-white/80 backdrop-blur">\n
                <div class="flex w-full items-center justify-between gap-6 px-6 py-4">\n
                    <div class="flex items-center gap-3">\n
                        <button type="button" id="clientSidebarToggle" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 text-slate-600 transition hover:border-teal-300 hover:text-teal-600 md:hidden" aria-label="Open menu">\n
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">\n
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />\n
                            </svg>\n
                        </button>\n
                        <div>\n
                            <div class="section-label">Client workspace</div>\n
                            <div class="text-lg font-semibold text-slate-900" data-current-page-title>Invoice</div>\n
                        </div>\n
                    </div>\n
                    <div class="flex flex-wrap items-center gap-3 md:gap-4">\n
                        <form method="POST" action="http://127.0.0.1:8000/client/system/cache/clear" data-native="true">\n
                            <input type="hidden" name="_token" value="pfb3giXMnek1piIrnHle03fVHrCSg8n4chNCdrd7" autocomplete="off">                            <button\n
                                type="submit"\n
                                class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"\n
                                title="Clears Laravel system caches and purges browser storage helpers"\n
                            >\n
                                Clear caches\n
                            </button>\n
                        </form>\n
                    </div>\n
                </div>\n
\n
                            </header>\n
\n
            <main class="w-full px-6 py-10 fade-in">\n
                <div\n
                    id="appContent"\n
                    data-page-title="Invoice"\n
                    data-page-heading="Invoice"\n
                    data-page-key="client.invoices.pay"\n
                >\n
                                            <div class="mb-8 rounded-3xl border border-amber-200 bg-amber-50 px-6 py-4 text-amber-800">\n
    <div class="flex flex-wrap items-center gap-3">\n
        <div class="text-xs uppercase tracking-[0.35em] text-amber-500">Payment due warning</div>\n
        <div class="flex-1 text-sm text-amber-700">\n
            <span class="font-semibold">Your account has outstanding invoices.</span>\n
            <span class="ml-1">\n
                                                                    1 unpaid\n
                                invoice(s) require attention.\n
            </span>\n
        </div>\n
                    <a href="http://127.0.0.1:8000/client/invoices/1/pay" class="inline-flex items-center rounded-full border border-amber-300 px-4 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100">\n
                Go to payment\n
            </a>\n
            </div>\n
</div>\n
                    \n
                    \n
                    \n
                    \n
                        \n
    <div class="invoice-container">\n
        <div class="invoice-grid invoice-header">\n
            <div class="invoice-col logo-wrap">\n
                                    <div class="invoice-logo-fallback">apptimatic</div>\n
                            </div>\n
            <div class="invoice-col text-right">\n
                <div class="invoice-status">\n
                    <span class="unpaid" style="text-transform: uppercase;">UNPAID</span>\n
                    <h3>Invoice: #1</h3>\n
                    <div style="margin-top: 0; font-size: 12px;">Invoice Date: <span class="small-text">22-02-2026</span></div>\n
                    <div style="margin-top: 0; font-size: 12px;">Invoice Due Date: <span class="small-text">01-03-2026</span></div>\n
                                    </div>\n
            </div>\n
        </div>\n
\n
        <hr />\n
\n
        <div class="invoice-grid invoice-addresses">\n
            <div class="invoice-col">\n
                <strong>Invoiced To</strong>\n
                <address class="small-text">\n
                    Invoice Client<br />\n
                    --<br />\n
                    --\n
                </address>\n
            </div>\n
            <div class="invoice-col right">\n
                <strong>Pay To</strong>\n
                <address class="small-text">\n
                    Apptimatic<br />\n
                    MyApptimatic<br />\n
                    email@demoemail.com\n
                </address>\n
            </div>\n
        </div>\n
\n
        <div class="panel panel-default">\n
            <div class="panel-body">\n
                <div class="table-responsive">\n
                    <table class="table table-condensed">\n
                        <thead>\n
                            <tr>\n
                                <td><strong>Description</strong></td>\n
                                <td width="20%" class="text-center"><strong>Amount</strong></td>\n
                            </tr>\n
                        </thead>\n
                        <tbody>\n
                                                        <tr>\n
                                <td class="total-row text-right"><strong>Sub Total</strong></td>\n
                                <td class="total-row text-center">USD 100.00</td>\n
                            </tr>\n
                                                        <tr>\n
                                <td class="total-row text-right"><strong>Discount</strong></td>\n
                                <td class="total-row text-center">- USD 0.00</td>\n
                            </tr>\n
                            <tr>\n
                                <td class="total-row text-right"><strong>Payable Amount</strong></td>\n
                                <td class="total-row text-center">USD 100.00</td>\n
                            </tr>\n
                        </tbody>\n
                    </table>\n
                </div>\n
            </div>\n
        </div>\n
\n
                    <div class="payment-panel no-print">\n
                <div class="payment-heading">Payment Method</div>\n
                \n
                                    <form method="POST" action="http://127.0.0.1:8000/client/invoices/1/checkout" id="gateway-form" class="gateway-form" data-native="true">\n
                        <input type="hidden" name="_token" value="pfb3giXMnek1piIrnHle03fVHrCSg8n4chNCdrd7" autocomplete="off">                        <label for="gateway-select" class="small-text"><strong>Select gateway</strong></label>\n
                        <select id="gateway-select" name="payment_gateway_id" class="form-control">\n
                                                            <option value="1">Manual / Bank Transfer</option>\n
                                                    </select>\n
                        <div id="gateway-instructions" class="small-text text-muted" style="margin-top: 10px;"></div>\n
                        <button type="submit" id="gateway-submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Pay now</button>\n
                    </form>\n
\n
                                        <script>\n
                        const gateways = [{"id":1,"name":"Manual \/ Bank Transfer","driver":"manual","payment_url":"","instructions":"","button_label":""}];\n
                        const gatewaySelect = document.getElementById('gateway-select');\n
                        const gatewayInstructions = document.getElementById('gateway-instructions');\n
                        const gatewaySubmit = document.getElementById('gateway-submit');\n
                        const gatewayForm = document.getElementById('gateway-form');\n
\n
                        function syncGatewayDetails() {\n
                            const selectedId = Number(gatewaySelect.value);\n
                            const selected = gateways.find((gateway) => gateway.id === selectedId);\n
\n
                            if (!selected) {\n
                                gatewayInstructions.textContent = '';\n
                                if (gatewaySubmit) gatewaySubmit.textContent = 'Pay now';\n
                                return;\n
                            }\n
\n
                            const instructions = selected.instructions || '';\n
                            gatewayInstructions.innerHTML = instructions\n
                                ? instructions.replace(/\n/g, '<br>')\n
                                : 'No additional instructions for this gateway.';\n
\n
                            if (gatewaySubmit) {\n
                                const label = (selected.button_label || '').trim();\n
                                gatewaySubmit.textContent = label ? label : `${selected.name} Pay`;\n
                            }\n
\n
                            if (gatewayForm) {\n
                                const openNew = selected.driver === 'bkash' && selected.payment_url;\n
                                gatewayForm.setAttribute('target', openNew ? '_blank' : '_self');\n
                            }\n
                        }\n
\n
                        gatewaySelect.addEventListener('change', syncGatewayDetails);\n
                        syncGatewayDetails();\n
                    </script>\n
                \n
                                    <div class="small-text text-muted" style="margin-top: 12px;">\n
                        Contact support to arrange payment.\n
                    </div>\n
                            </div>\n
        \n
        <div class="container-fluid invoice-container">\n
            <div class="row mt-5" style="display: flex !important; justify-content: center;">\n
                <div class="invoice-col full no-print" style="text-align: center;">\n
                    <div class="flex flex-wrap items-center justify-center gap-2">\n
                        <a href="http://127.0.0.1:8000/client/invoices/1/download" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Download</a>\n
                        <a href="javascript:window.print()" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Print</a>\n
                    </div>\n
                </div>\n
                <div class="invoice-col full" style="text-align: center;">\n
                    <div class="mb-3">\n
                        <p>This is system generated invoice no signature required</p>\n
                    </div>\n
                </div>\n
            </div>\n
        </div>\n
    </div>\n
                </div>\n
            </main>\n
        </div>\n
    </div>\n
\n
    <script>\n
        document.addEventListener('DOMContentLoaded', () => {\n
            const sidebar = document.getElementById('clientSidebar');\n
            const overlay = document.getElementById('clientSidebarOverlay');\n
            const openBtn = document.getElementById('clientSidebarToggle');\n
            const closeBtn = document.getElementById('clientSidebarClose');\n
\n
            const openSidebar = () => {\n
                sidebar?.classList.remove('-translate-x-full');\n
                overlay?.classList.remove('opacity-0', 'pointer-events-none');\n
            };\n
\n
            const closeSidebar = () => {\n
                sidebar?.classList.add('-translate-x-full');\n
                overlay?.classList.add('opacity-0', 'pointer-events-none');\n
            };\n
\n
            openBtn?.addEventListener('click', openSidebar);\n
            closeBtn?.addEventListener('click', closeSidebar);\n
            overlay?.addEventListener('click', closeSidebar);\n
            document.addEventListener('keydown', (event) => {\n
                if (event.key === 'Escape') {\n
                    closeSidebar();\n
                }\n
            });\n
        });\n
    </script>\n
            <style>\n
    #ajaxModal.hidden {\n
        display: none;\n
    }\n
\n
    .is-invalid {\n
        border-color: rgb(248 113 113) !important;\n
    }\n
\n
    .invalid-feedback {\n
        margin-top: 0.25rem;\n
        font-size: 0.75rem;\n
        color: rgb(185 28 28);\n
    }\n
</style>\n
\n
<div id="ajaxModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">\n
    <div class="absolute inset-0 bg-slate-900/60" data-ajax-modal-backdrop></div>\n
    <div class="relative mx-auto flex min-h-full max-w-3xl items-center justify-center px-4 py-8">\n
        <div class="w-full rounded-2xl border border-slate-200 bg-white shadow-2xl">\n
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">\n
                <div id="ajaxModalTitle" class="text-sm font-semibold text-slate-900">Form</div>\n
                <button type="button" id="ajaxModalClose" class="rounded-full border border-slate-200 p-2 text-slate-500 hover:border-slate-300 hover:text-slate-700" aria-label="Close">\n
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">\n
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />\n
                    </svg>\n
                </button>\n
            </div>\n
            <div id="ajaxModalBody" class="max-h-[75vh] overflow-y-auto p-5"></div>\n
        </div>\n
    </div>\n
</div>\n
    <div id="delete-confirm-modal" class="fixed inset-0 z-[60] hidden" aria-hidden="true">\n
    <div class="absolute inset-0 bg-slate-900/60" data-delete-confirm-backdrop></div>\n
    <div class="relative mx-auto flex min-h-full max-w-2xl items-center justify-center px-4 py-10">\n
        <div class="w-full rounded-2xl bg-white shadow-2xl">\n
            <div class="flex items-start justify-between border-b border-slate-300 px-6 py-5">\n
                <div>\n
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Delete</div>\n
                    <div data-delete-confirm-title class="mt-2 text-lg font-semibold text-slate-900">Delete this item?</div>\n
                </div>\n
                <button type="button" data-delete-confirm-close class="rounded-full border border-slate-300 p-2 text-slate-400 hover:border-slate-300 hover:text-slate-600" aria-label="Close">\n
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">\n
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />\n
                    </svg>\n
                </button>\n
            </div>\n
            <div class="px-6 py-5 text-sm text-slate-600">\n
                <p data-delete-confirm-description class="leading-relaxed">\n
                    This action cannot be undone.\n
                </p>\n
                <div class="mt-5">\n
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-400">\n
                        Type "<span data-delete-confirm-phrase class="font-semibold text-slate-700">DELETE</span>" to confirm\n
                    </label>\n
                    <input\n
                        id="delete-confirm-input"\n
                        type="text"\n
                        autocomplete="off"\n
                        class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 focus:border-rose-300 focus:ring-0"\n
                        placeholder="Type the confirmation text"\n
                    />\n
                    <div data-delete-confirm-hint class="mt-2 text-xs text-slate-400"></div>\n
                </div>\n
            </div>\n
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">\n
                <button type="button" data-delete-confirm-cancel class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300">\n
                    Cancel\n
                </button>\n
                <button type="button" data-delete-confirm-submit class="rounded-full bg-rose-500 px-6 py-2 text-xs font-semibold text-white opacity-50" disabled>\n
                    Delete\n
                </button>\n
            </div>\n
        </div>\n
    </div>\n
</div>\n
\n
<script>\n
    document.addEventListener('DOMContentLoaded', () => {\n
        const modal = document.getElementById('delete-confirm-modal');\n
        if (!modal) {\n
            return;\n
        }\n
\n
        const titleEl = modal.querySelector('[data-delete-confirm-title]');\n
        const descriptionEl = modal.querySelector('[data-delete-confirm-description]');\n
        const phraseEl = modal.querySelector('[data-delete-confirm-phrase]');\n
        const hintEl = modal.querySelector('[data-delete-confirm-hint]');\n
        const inputEl = modal.querySelector('#delete-confirm-input');\n
        const submitBtn = modal.querySelector('[data-delete-confirm-submit]');\n
        const cancelBtn = modal.querySelector('[data-delete-confirm-cancel]');\n
        const closeBtn = modal.querySelector('[data-delete-confirm-close]');\n
        const backdrop = modal.querySelector('[data-delete-confirm-backdrop]');\n
\n
        let activeForm = null;\n
        let requiredPhrase = 'DELETE';\n
\n
        const setButtonState = (enabled) => {\n
            if (!submitBtn) return;\n
            submitBtn.disabled = !enabled;\n
            submitBtn.classList.toggle('opacity-50', !enabled);\n
        };\n
\n
        const openModal = (form) => {\n
            activeForm = form;\n
\n
            const name = (form.getAttribute('data-confirm-name') || '').trim();\n
            requiredPhrase = name !== '' ? name : 'DELETE';\n
            const title = form.getAttribute('data-confirm-title') || (name ? `Delete ${name}?` : 'Delete this item?');\n
            const description = form.getAttribute('data-confirm-description') || 'This action cannot be undone.';\n
            const actionLabel = form.getAttribute('data-confirm-action') || 'Delete';\n
            const hint = form.getAttribute('data-confirm-hint') || '';\n
\n
            if (titleEl) titleEl.textContent = title;\n
            if (descriptionEl) descriptionEl.textContent = description;\n
            if (phraseEl) phraseEl.textContent = requiredPhrase;\n
            if (hintEl) hintEl.textContent = hint;\n
            if (submitBtn) submitBtn.textContent = actionLabel;\n
\n
            if (inputEl) {\n
                inputEl.value = '';\n
                inputEl.placeholder = `Type "${requiredPhrase}" to confirm`;\n
            }\n
\n
            setButtonState(false);\n
            modal.classList.remove('hidden');\n
            modal.setAttribute('aria-hidden', 'false');\n
            document.body.classList.add('overflow-hidden');\n
            setTimeout(() => inputEl?.focus(), 0);\n
        };\n
\n
        const closeModal = () => {\n
            modal.classList.add('hidden');\n
            modal.setAttribute('aria-hidden', 'true');\n
            document.body.classList.remove('overflow-hidden');\n
            activeForm = null;\n
            requiredPhrase = 'DELETE';\n
        };\n
\n
        const handleDeleteRequest = (event, form) => {\n
            if (!form || !form.hasAttribute('data-delete-confirm')) {\n
                return false;\n
            }\n
\n
            event.preventDefault();\n
            event.stopPropagation();\n
            event.stopImmediatePropagation();\n
            openModal(form);\n
            return true;\n
        };\n
\n
        document.addEventListener('click', (event) => {\n
            const trigger = event.target.closest('button[type="submit"], input[type="submit"]');\n
            if (!trigger) {\n
                return;\n
            }\n
\n
            const form = trigger.form || trigger.closest('form');\n
            handleDeleteRequest(event, form);\n
        }, true);\n
\n
        document.addEventListener('submit', (event) => {\n
            const form = event.target;\n
            if (!(form instanceof HTMLFormElement)) {\n
                return;\n
            }\n
\n
            handleDeleteRequest(event, form);\n
        }, true);\n
\n
        inputEl?.addEventListener('input', () => {\n
            const value = (inputEl.value || '').trim();\n
            setButtonState(value === requiredPhrase);\n
        });\n
\n
        inputEl?.addEventListener('keydown', (event) => {\n
            if (event.key !== 'Enter') return;\n
            event.preventDefault();\n
            if ((inputEl.value || '').trim() === requiredPhrase) {\n
                submitBtn?.click();\n
            }\n
        });\n
\n
        submitBtn?.addEventListener('click', () => {\n
            if (!activeForm) return;\n
            const value = (inputEl?.value || '').trim();\n
            if (value !== requiredPhrase) return;\n
            const formToSubmit = activeForm;\n
            closeModal();\n
            formToSubmit.submit();\n
        });\n
\n
        cancelBtn?.addEventListener('click', closeModal);\n
        closeBtn?.addEventListener('click', closeModal);\n
        backdrop?.addEventListener('click', closeModal);\n
\n
        document.addEventListener('keydown', (event) => {\n
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {\n
                closeModal();\n
            }\n
        });\n
    });\n
</script>\n
    <script>\n
    document.addEventListener('DOMContentLoaded', () => {\n
        document.querySelectorAll('table').forEach((table) => {\n
            if (table.closest('[data-table-responsive]')) {\n
                return;\n
            }\n
            const wrapper = document.createElement('div');\n
            wrapper.setAttribute('data-table-responsive', 'true');\n
            wrapper.className = 'overflow-x-auto';\n
            const parent = table.parentElement;\n
            if (parent) {\n
                parent.insertBefore(wrapper, table);\n
                wrapper.appendChild(table);\n
            }\n
        });\n
    });\n
</script>\n
    <div id="pageScriptStack" hidden aria-hidden="true">\n
            </div>\n
</body>\n
</html>\n
' [ASCII](length: 34072) contains "Invoice #1" [ASCII](length: 10).

C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponseAssert.php:45
C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponse.php:620
C:\xampp\htdocs\myapptimatic\tests\Feature\ClientInvoiceAccessTest.php:43

2) Tests\Feature\EmployeeActivityTrackingTest::employee_login_creates_session_and_daily_row
Failed asserting that two strings are equal.

The following errors occurred during the last request:

The provided credentials are incorrect.

--- Expected
+++ Actual
@@ @@
-'http://127.0.0.1:8000/employee/dashboard'
+'http://127.0.0.1:8000/employee/login'

C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponseAssert.php:45
C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponse.php:342
C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponse.php:191
C:\xampp\htdocs\myapptimatic\tests\Feature\EmployeeActivityTrackingTest.php:46

3) Tests\Feature\EmployeeTaskCompletionTest::task_with_subtasks_cannot_be_completed_manually
Session missing error: status
Failed asserting that false is true.

The following errors occurred during the last request:

Main task status is controlled by subtasks when subtasks exist.

C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponseAssert.php:45
C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponse.php:1528
C:\xampp\htdocs\myapptimatic\tests\Feature\EmployeeTaskCompletionTest.php:53

4) Tests\Feature\TaskQuickAccessTest::employee_cannot_complete_task_with_subtasks
Session missing error: status
Failed asserting that false is true.

The following errors occurred during the last request:

Main task status is controlled by subtasks when subtasks exist.

C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponseAssert.php:45
C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponse.php:1528
C:\xampp\htdocs\myapptimatic\tests\Feature\TaskQuickAccessTest.php:316

5) Tests\Feature\UserDocumentDisplayTest::customer_list_shows_avatar_when_available
Failed asserting that '<!DOCTYPE html>\n
<html lang="en">\n
<head>\n
    <meta charset="utf-8">\n
<meta name="viewport" content="width=device-width, initial-scale=1">\n
<meta name="csrf-token" content="fDknEnmEjfc4YXjPyPurO1aIqKvQG9tL5niHOK6A">\n
<title>Customers</title>\n
<link rel="preconnect" href="https://fonts.googleapis.com">\n
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">\n
<script src="https://cdn.tailwindcss.com"></script>\n
<link rel="preload" as="style" href="http://127.0.0.1:8000/build/assets/app-CD_PM2Md.css" /><link rel="modulepreload" href="http://127.0.0.1:8000/build/assets/app-Nj5TQMYn.js" /><link rel="stylesheet" href="http://127.0.0.1:8000/build/assets/app-CD_PM2Md.css" /><script type="module" src="http://127.0.0.1:8000/build/assets/app-Nj5TQMYn.js"></script></head>\n
<body class="bg-dashboard">\n
    <div class="min-h-screen flex flex-col md:flex-row">\n
        <div id="sidebarOverlay" class="fixed inset-0 z-20 bg-slate-900/60 opacity-0 pointer-events-none transition-opacity duration-200 md:hidden"></div>\n
        <aside id="adminSidebar" class="sidebar fixed inset-y-0 left-0 z-30 flex w-72 max-w-[90vw] flex-shrink-0 flex-col px-6 py-7 overflow-y-auto max-h-screen transform transition-transform duration-200 ease-in-out -translate-x-full md:w-64 md:max-w-none md:translate-x-0 md:overflow-y-auto md:max-h-screen md:sticky md:top-0">\n
                        <div class="flex items-center gap-3">\n
                                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white/10 text-lg font-semibold text-white">LM</div>\n
                                <div>\n
                    <div class="text-xs uppercase tracking-[0.35em] text-slate-400">Admin</div>\n
                    <div class="text-lg font-semibold text-white">Apptimatic</div>\n
                </div>\n
            </div>\n
                        <button type="button" id="sidebarClose" class="absolute right-4 top-4 rounded-full border border-white/10 bg-white/10 p-2 text-slate-200 transition hover:bg-white/20 md:hidden" aria-label="Close menu">\n
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">\n
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />\n
                </svg>\n
            </button>\n
\n
                        <nav class="mt-10 space-y-4 text-sm">\n
                                    <div>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/dashboard"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Dashboard\n
    </a>\n
                    </div>\n
\n
                    <div class="space-y-2">\n
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Sales & Customers</div>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/customers"\n
    class="nav-link nav-link-active"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Customers\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/orders"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Orders\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/sales-reps"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Sales Representatives\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/affiliates"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Affiliates\n
    </a>\n
                    </div>\n
\n
                    <div class="space-y-2">\n
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Delivery & Services</div>\n
                        \n
                        \n
                        <div>\n
    <a \n
        href="http://127.0.0.1:8000/admin/projects"\n
        class="nav-link"\n
    >\n
                    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                Projects\n
            </a>\n
    \n
            <div class="ml-8 space-y-1 text-xs text-slate-400">\n
            <a href="http://127.0.0.1:8000/admin/projects" class="block hover:text-slate-200">All Projects</a>\n
                            <a href="http://127.0.0.1:8000/admin/projects/create" class="block hover:text-slate-200">Create Project</a>\n
                            <a href="http://127.0.0.1:8000/admin/project-maintenances" class="block hover:text-slate-200">Maintenance</a>\n
        </div>\n
    </div>\n
                                                    <a \n
    href="http://127.0.0.1:8000/admin/tasks"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                                <span>Tasks</span>\n
                                <span class="ml-auto ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">0</span>\n
    </a>\n
                                                \n
                        <a \n
    href="http://127.0.0.1:8000/admin/subscriptions"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Subscriptions\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/licenses"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Licenses\n
    </a>\n
                    </div>\n
\n
                    <div class="space-y-2">\n
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Billing & Finance</div>\n
                        \n
                        \n
                        <div>\n
    <a \n
        href="http://127.0.0.1:8000/admin/invoices"\n
        class="nav-link"\n
    >\n
                    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                Invoices\n
            </a>\n
    \n
            <div class="ml-8 space-y-1 text-xs text-slate-400">\n
            <a href="http://127.0.0.1:8000/admin/invoices" class="block hover:text-slate-200">All invoices</a>\n
                            <a href="http://127.0.0.1:8000/admin/invoices/paid" class="block hover:text-slate-200">Paid</a>\n
                            <a href="http://127.0.0.1:8000/admin/invoices/unpaid" class="block hover:text-slate-200">Unpaid</a>\n
                            <a href="http://127.0.0.1:8000/admin/invoices/overdue" class="block hover:text-slate-200">Overdue</a>\n
                            <a href="http://127.0.0.1:8000/admin/invoices/cancelled" class="block hover:text-slate-200">Cancelled</a>\n
                            <a href="http://127.0.0.1:8000/admin/invoices/refunded" class="block hover:text-slate-200">Refunded</a>\n
        </div>\n
    </div>\n
                        \n
                        <a \n
    href="http://127.0.0.1:8000/admin/payment-proofs"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            <span>Manual Payments</span>\n
    </a>\n
                        <div>\n
    <a \n
        href="http://127.0.0.1:8000/admin/accounting"\n
        class="nav-link"\n
    >\n
                    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                Accounting\n
            </a>\n
    \n
            <div class="ml-8 space-y-1 text-xs text-slate-400">\n
            <a href="http://127.0.0.1:8000/admin/accounting" class="block hover:text-slate-200">Ledger</a>\n
                            <a href="http://127.0.0.1:8000/admin/accounting/transactions" class="block hover:text-slate-200">Transactions</a>\n
                            <a href="http://127.0.0.1:8000/admin/accounting/refunds" class="block hover:text-slate-200">Refunds</a>\n
                            <a href="http://127.0.0.1:8000/admin/accounting/credits" class="block hover:text-slate-200">Credits</a>\n
                            <a href="http://127.0.0.1:8000/admin/accounting/expenses" class="block hover:text-slate-200">Expenses</a>\n
        </div>\n
    </div>\n
                                                    <div>\n
    <a \n
        href="http://127.0.0.1:8000/admin/income"\n
        class="nav-link"\n
    >\n
                    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                Income\n
            </a>\n
    \n
            <div class="ml-8 space-y-1 text-xs text-slate-400">\n
            <a href="http://127.0.0.1:8000/admin/income/dashboard" class="block hover:text-slate-200">Dashboard</a>\n
                                <a href="http://127.0.0.1:8000/admin/income/carrothost" class="block hover:text-slate-200">CarrotHost</a>\n
                                <a href="http://127.0.0.1:8000/admin/income" class="block hover:text-slate-200">All income</a>\n
                                <a href="http://127.0.0.1:8000/admin/income/create" class="block hover:text-slate-200">Add income</a>\n
                                <a href="http://127.0.0.1:8000/admin/income/categories" class="block hover:text-slate-200">Categories</a>\n
        </div>\n
    </div>\n
                            <div>\n
    <a \n
        href="http://127.0.0.1:8000/admin/expenses"\n
        class="nav-link"\n
    >\n
                    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                Expenses\n
            </a>\n
    \n
            <div class="ml-8 space-y-1 text-xs text-slate-400">\n
            <a href="http://127.0.0.1:8000/admin/expenses/dashboard" class="block hover:text-slate-200">Dashboard</a>\n
                                <a href="http://127.0.0.1:8000/admin/expenses" class="block hover:text-slate-200">All expenses</a>\n
                                <a href="http://127.0.0.1:8000/admin/expenses/recurring" class="block hover:text-slate-200">Recurring</a>\n
                                <a href="http://127.0.0.1:8000/admin/expenses/categories" class="block hover:text-slate-200">Categories</a>\n
        </div>\n
    </div>\n
                            <a \n
    href="http://127.0.0.1:8000/admin/finance/tax"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                                Tax Settings\n
    </a>\n
                            <a \n
    href="http://127.0.0.1:8000/admin/finance/payment-methods"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                                Payment Methods\n
    </a>\n
                            <a \n
    href="http://127.0.0.1:8000/admin/finance/reports"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                                Finance Reports\n
    </a>\n
                                                <a \n
    href="http://127.0.0.1:8000/admin/payment-gateways"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Payment Gateways\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/commission-payouts"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Commission Payouts\n
    </a>\n
                    </div>\n
\n
                    <div class="space-y-2">\n
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Products & Plans</div>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/products"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Products\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/plans"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Plans\n
    </a>\n
                    </div>\n
\n
                    <div class="space-y-2">\n
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">People (HR)</div>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/hr/dashboard"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            HR Dashboard\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/hr/employees"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Employees\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/users/activity-summary"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Activity Summary\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/hr/work-logs"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Work Logs\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/hr/leave-types"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Leave Types\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/hr/leave-requests"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Leave Requests\n
            <span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">0</span>\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/hr/attendance"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Attendance\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/hr/paid-holidays"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Paid Holidays\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/hr/payroll"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Payroll\n
    </a>\n
                    </div>\n
\n
                    <div class="space-y-2">\n
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Support & Communication</div>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/support-tickets"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            <span>Support</span>\n
            <span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">0</span>\n
    </a>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/chats"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            <span>Chat</span>\n
                            <span class="ml-auto ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">0</span>\n
    </a>\n
                                                <div>\n
                                                            <a\n
                                    href="http://127.0.0.1:8000/admin/apptimatic-email"\n
                                    class="nav-link"\n
                                >\n
                                    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                                    <span>Apptimatic Email</span>\n
                                </a>\n
                                                        <div class="ml-8 mt-1 space-y-1 text-xs">\n
                                                                    <a href="http://127.0.0.1:8000/admin/apptimatic-email" class="flex items-center gap-2 hover:text-slate-200">\n
                                        <span>Inbox</span>\n
                                        <span class="ml-auto rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">0</span>\n
                                    </a>\n
                                                                <span class="block cursor-not-allowed text-slate-500/70" title="Coming soon">Sent</span>\n
                                <span class="block cursor-not-allowed text-slate-500/70" title="Coming soon">Drafts</span>\n
                                <span class="block cursor-not-allowed text-slate-500/70" title="Coming soon">Trash</span>\n
                            </div>\n
                        </div>\n
                    </div>\n
\n
                    <div class="space-y-2">\n
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Administration</div>\n
                                                                            <div class="space-y-1">\n
                                <a \n
    href="http://127.0.0.1:8000/admin/user/master_admin"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                                    Master Admins\n
    </a>\n
                                <a \n
    href="http://127.0.0.1:8000/admin/user/sub_admin"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                                    Sub Admins\n
    </a>\n
                                <a \n
    href="http://127.0.0.1:8000/admin/user/support"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                                    Support Users\n
    </a>\n
                            </div>\n
                                                <a \n
    href="http://127.0.0.1:8000/admin/profile"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Profile\n
    </a>\n
                    </div>\n
\n
                    <div class="space-y-2">\n
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">System & Monitoring</div>\n
                        <a \n
    href="http://127.0.0.1:8000/admin/automation-status"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Automation Status\n
    </a>\n
                        \n
                        <a \n
    href="http://127.0.0.1:8000/admin/logs/activity"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Logs\n
    </a>\n
                        \n
                        <a \n
    href="http://127.0.0.1:8000/admin/settings"\n
    class="nav-link"\n
>\n
    <span class="h-2 w-2 rounded-full bg-current"></span>\n
                            Settings\n
    </a>\n
                    </div>\n
                            </nav>\n
\n
                        <div class="mt-auto">\n
                <div class="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-200">\n
                    <div class="flex items-center gap-3">\n
                        <div class="h-10 w-10 overflow-hidden rounded-full border border-white/10 bg-white/10">\n
                            <div class="h-10 w-10 rounded-full overflow-hidden flex items-center justify-center bg-slate-100 text-slate-600 text-sm font-semibold">\n
            <span>WR</span>\n
    </div>\n
                        </div>\n
                        <div class="min-w-0">\n
                            <div class="truncate text-sm font-semibold text-white">Willie Reichert</div>\n
                            <div class="text-[11px] text-slate-400">Master Administrator</div>\n
                        </div>\n
                    </div>\n
                    <form method="POST" action="http://127.0.0.1:8000/logout" class="mt-3">\n
                        <input type="hidden" name="_token" value="fDknEnmEjfc4YXjPyPurO1aIqKvQG9tL5niHOK6A" autocomplete="off">                        <button type="submit" class="w-full rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-white/20">\n
                            Sign out\n
                        </button>\n
                    </form>\n
                </div>\n
            </div>\n
        </aside>\n
\n
        <div class="flex-1 flex flex-col w-full min-w-0">\n
            <header class="sticky top-0 z-20 border-b border-slate-300/70 bg-white/80 backdrop-blur">\n
                <div class="flex w-full items-center justify-between gap-6 px-6 py-4">\n
                    <div class="flex items-center gap-3">\n
                        <button type="button" id="sidebarToggle" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 text-slate-600 transition hover:border-teal-300 hover:text-teal-600 md:hidden" aria-label="Open menu">\n
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">\n
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />\n
                            </svg>\n
                        </button>\n
                        <div>\n
                            <div class="section-label">Admin workspace</div>\n
                            <div class="text-lg font-semibold text-slate-900" data-current-page-title>Customers</div>\n
                        </div>\n
                    </div>\n
                                                                <div class="stats hidden flex-wrap items-center gap-3 text-xs text-slate-500 lg:flex">\n
                                                            \n
                                <a href="http://127.0.0.1:8000/admin/orders?status=pending" class="flex items-center gap-2">\n
                                    <span class="stat">0</span>\n
                                    Pending Orders\n
                                </a>\n
                                <span class="text-slate-300">|</span>\n
                                <a href="http://127.0.0.1:8000/admin/invoices/overdue" class="flex items-center gap-2">\n
                                    <span class="stat">0</span>\n
                                    Overdue Invoices\n
                                </a>\n
                                <span class="text-slate-300">|</span>\n
                                                                                        \n
                                <a href="http://127.0.0.1:8000/admin/support-tickets?status=customer_reply" class="flex items-center gap-2">\n
                                    <span class="stat">0</span>\n
                                    Ticket(s) Awaiting Reply\n
                                </a>\n
                                                    </div>\n
                                        <div class="flex flex-wrap items-center gap-3 md:gap-4">\n
                        <form method="POST" action="http://127.0.0.1:8000/admin/system/cache/clear" data-native="true">\n
                            <input type="hidden" name="_token" value="fDknEnmEjfc4YXjPyPurO1aIqKvQG9tL5niHOK6A" autocomplete="off">                            <button\n
                                type="submit"\n
                                class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"\n
                                title="Clears Laravel system caches and purges browser storage helpers"\n
                            >\n
                                Clear caches\n
                            </button>\n
                        </form>\n
                    </div>\n
                </div>\n
\n
                            </header>\n
\n
            <main id="main-content" class="w-full px-6 py-10 fade-in">\n
                <div\n
                    id="appContent"\n
                    data-page-title="Customers"\n
                    data-page-heading="Customers"\n
                    data-page-key="admin.customers.index"\n
                >\n
                    \n
                    \n
                        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">\n
        <div class="flex-1">\n
            <form id="customersSearchForm" method="GET" action="http://127.0.0.1:8000/admin/customers" class="flex items-center gap-3" data-live-filter="true">\n
                <div class="relative">\n
                    <input\n
                        type="text"\n
                        name="search"\n
                        value=""\n
                        placeholder="Search customers..."\n
                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"\n
                    />\n
                </div>\n
            </form>\n
        </div>\n
        <a href="http://127.0.0.1:8000/admin/customers/create" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Customer</a>\n
    </div>\n
\n
    <div id="customersTable">\n
    <div class="card overflow-hidden">\n
        <div class="overflow-x-auto">\n
            <table class="min-w-full text-left text-sm">\n
                <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">\n
                    <tr>\n
                        <th class="px-4 py-3">ID</th>\n
                        <th class="px-4 py-3">Name & Company</th>\n
                        <th class="px-4 py-3">Email</th>\n
                        <th class="px-4 py-3">Services</th>\n
                        <th class="px-4 py-3">Projects & Maintenance</th>\n
                        <th class="px-4 py-3">Created</th>\n
                        <th class="px-4 py-3">Login</th>\n
                        <th class="px-4 py-3">Status</th>\n
                    </tr>\n
                </thead>\n
                <tbody>\n
                                            <tr class="border-b border-slate-100">\n
                            <td class="px-4 py-3 text-slate-500"><a href="http://127.0.0.1:8000/admin/customers/1" class="hover:text-teal-600">1</a></td>\n
                            <td class="px-4 py-3">\n
                                <div>\n
                                    <div>\n
                                        <a href="http://127.0.0.1:8000/admin/customers/1" class="font-medium text-slate-900 hover:text-teal-600">\n
                                            Avatar Client\n
                                        </a>\n
                                        <div class="text-xs text-slate-500">--</div>\n
                                    </div>\n
                                </div>\n
                            </td>\n
                            <td class="px-4 py-3 text-slate-500"></td>\n
                            <td class="px-4 py-3 text-slate-500">\n
                                0 (0)\n
                            </td>\n
                            <td class="px-4 py-3 text-slate-500">\n
                                <div class="text-sm text-slate-700">Projects: 0</div>\n
                                <div class="text-xs text-slate-500">Maintenance: 0</div>\n
                            </td>\n
                            <td class="px-4 py-3 text-slate-500">22-02-2026</td>                            \n
                            <td class="px-4 py-3">\n
                                                                \n
                                <div class="mt-1 text-[11px] text-slate-400">\n
                                    Last login: --\n
                                </div>\n
                            </td>\n
                            <td class="px-4 py-3">\n
                                                                <div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">\n
    Active\n
</div>\n
                            </td>\n
                        </tr>\n
                                    </tbody>\n
            </table>\n
        </div>\n
    </div>\n
\n
    <div class="mt-4">\n
        \n
    </div>\n
</div>\n
                </div>\n
            </main>\n
        </div>\n
    </div>\n
    \n
    <script>\n
          const bindInvoiceItems = (root = document) => {\n
              const container = root.querySelector('#invoiceItems');\n
              const addBtn = root.querySelector('#addInvoiceItem');\n
\n
              if (!container || !addBtn) return;\n
              if (container.dataset.itemsBound === 'true') return;\n
\n
              container.dataset.itemsBound = 'true';\n
\n
              const renumber = () => {\n
                  container.querySelectorAll('.invoice-item').forEach((row, index) => {\n
                      row.querySelectorAll('input').forEach((input) => {\n
                          const name = input.getAttribute('name') || '';\n
                          const updated = name.replace(/items\[\d+\]/, `items[${index}]`);\n
                          input.setAttribute('name', updated);\n
                      });\n
                  });\n
              };\n
\n
              const addRow = () => {\n
                  const template = container.querySelector('.invoice-item');\n
                  if (!template) return;\n
                  const clone = template.cloneNode(true);\n
                  clone.querySelectorAll('input').forEach((input) => {\n
                      if (input.type === 'number') {\n
                          input.value = input.name.includes('[quantity]') ? '1' : '0';\n
                      } else {\n
                          input.value = '';\n
                      }\n
                  });\n
                  container.appendChild(clone);\n
                  renumber();\n
              };\n
\n
              addBtn.addEventListener('click', addRow);\n
              container.addEventListener('click', (event) => {\n
                  const btn = event.target.closest('.removeInvoiceItem');\n
                  if (!btn) return;\n
                  const row = btn.closest('.invoice-item');\n
                  if (!row) return;\n
                  if (container.querySelectorAll('.invoice-item').length === 1) {\n
                      row.querySelectorAll('input').forEach((input) => {\n
                          input.value = input.type === 'number' && input.name.includes('[quantity]') ? '1' : '';\n
                      });\n
                      return;\n
                  }\n
                  row.remove();\n
                  renumber();\n
              });\n
          };\n
\n
          window.bindInvoiceItems = bindInvoiceItems;\n
\n
          document.addEventListener('DOMContentLoaded', () => {\n
              const sidebar = document.getElementById('adminSidebar');\n
              const overlay = document.getElementById('sidebarOverlay');\n
              const openBtn = document.getElementById('sidebarToggle');\n
              const closeBtn = document.getElementById('sidebarClose');\n
              const globalWorkTimerCard = document.getElementById('global-work-timer');\n
\n
            const openSidebar = () => {\n
                sidebar?.classList.remove('-translate-x-full');\n
                overlay?.classList.remove('opacity-0', 'pointer-events-none');\n
            };\n
\n
            const closeSidebar = () => {\n
                sidebar?.classList.add('-translate-x-full');\n
                overlay?.classList.add('opacity-0', 'pointer-events-none');\n
            };\n
\n
            openBtn?.addEventListener('click', openSidebar);\n
            closeBtn?.addEventListener('click', closeSidebar);\n
            overlay?.addEventListener('click', closeSidebar);\n
              document.addEventListener('keydown', (event) => {\n
                  if (event.key === 'Escape') {\n
                      closeSidebar();\n
                  }\n
              });\n
\n
              bindInvoiceItems();\n
\n
              const setupGlobalWorkTimer = () => {\n
                  if (!globalWorkTimerCard) {\n
                      return;\n
                  }\n
\n
                  const summaryUrl = globalWorkTimerCard.dataset.summaryUrl;\n
                  const pingUrl = globalWorkTimerCard.dataset.pingUrl;\n
                  const timeEl = globalWorkTimerCard.querySelector('[data-global-work-time]');\n
                  const statusEl = globalWorkTimerCard.querySelector('[data-global-work-status]');\n
                  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';\n
\n
                  if (!summaryUrl || !timeEl || !statusEl) {\n
                      return;\n
                  }\n
\n
                  const formatSeconds = (seconds) => {\n
                      const total = Math.max(0, Number(seconds || 0));\n
                      const hours = Math.floor(total / 3600);\n
                      const minutes = Math.floor((total % 3600) / 60);\n
                      const secs = Math.floor(total % 60);\n
                      return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;\n
                  };\n
\n
                  let activeSeconds = 0;\n
                  let isActive = false;\n
\n
                  const setStatus = (status, active) => {\n
                      if (!active) {\n
                          statusEl.textContent = 'Stopped';\n
                          return;\n
                      }\n
\n
                      statusEl.textContent = status === 'idle' ? 'Idle' : 'Working';\n
                  };\n
\n
                  const applyPayload = (payload) => {\n
                      activeSeconds = Number(payload?.active_seconds || 0);\n
                      isActive = Boolean(payload?.is_active);\n
                      timeEl.textContent = formatSeconds(activeSeconds);\n
                      setStatus(payload?.status || 'stopped', isActive);\n
                  };\n
\n
                  const fetchSummary = async () => {\n
                      try {\n
                          const response = await fetch(summaryUrl, {\n
                              headers: {\n
                                  'Accept': 'application/json',\n
                                  'X-Requested-With': 'XMLHttpRequest',\n
                              },\n
                          });\n
                          if (!response.ok) {\n
                              return;\n
                          }\n
                          const json = await response.json();\n
                          applyPayload(json?.data || null);\n
                      } catch (error) {\n
                          // Silent fail for sidebar widget\n
                      }\n
                  };\n
\n
                  const pingSession = async () => {\n
                      if (!pingUrl || !csrfToken) {\n
                          return null;\n
                      }\n
\n
                      const formData = new FormData();\n
                      formData.append('_token', csrfToken);\n
\n
                      try {\n
                          const response = await fetch(pingUrl, {\n
                              method: 'POST',\n
                              headers: {\n
                                  'Accept': 'application/json',\n
                                  'X-Requested-With': 'XMLHttpRequest',\n
                              },\n
                              body: formData,\n
                          });\n
                          if (!response.ok) {\n
                              return null;\n
                          }\n
                          const json = await response.json();\n
                          return json?.data || null;\n
                      } catch (error) {\n
                          return null;\n
                      }\n
                  };\n
\n
                  window.addEventListener('employee-work-session:update', (event) => {\n
                      applyPayload(event.detail || null);\n
                  });\n
\n
                  setInterval(() => {\n
                      if (!isActive) {\n
                          return;\n
                      }\n
                      activeSeconds += 1;\n
                      timeEl.textContent = formatSeconds(activeSeconds);\n
                  }, 1000);\n
\n
                  setInterval(async () => {\n
                      if (!isActive || document.visibilityState !== 'visible') {\n
                          return;\n
                      }\n
                      const data = await pingSession();\n
                      if (data) {\n
                          applyPayload(data);\n
                      }\n
                  }, 60000);\n
\n
                  setInterval(fetchSummary, 10000);\n
                  fetchSummary();\n
              };\n
\n
              setupGlobalWorkTimer();\n
\n
              document.addEventListener('ajax:content:loaded', (event) => {\n
                  bindInvoiceItems(event.detail?.content || document);\n
              });\n
          });\n
      </script>\n
            <style>\n
    #ajaxModal.hidden {\n
        display: none;\n
    }\n
\n
    .is-invalid {\n
        border-color: rgb(248 113 113) !important;\n
    }\n
\n
    .invalid-feedback {\n
        margin-top: 0.25rem;\n
        font-size: 0.75rem;\n
        color: rgb(185 28 28);\n
    }\n
</style>\n
\n
<div id="ajaxModal" class="fixed inset-0 z-[70] hidden" aria-hidden="true">\n
    <div class="absolute inset-0 bg-slate-900/60" data-ajax-modal-backdrop></div>\n
    <div class="relative mx-auto flex min-h-full max-w-3xl items-center justify-center px-4 py-8">\n
        <div class="w-full rounded-2xl border border-slate-200 bg-white shadow-2xl">\n
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">\n
                <div id="ajaxModalTitle" class="text-sm font-semibold text-slate-900">Form</div>\n
                <button type="button" id="ajaxModalClose" class="rounded-full border border-slate-200 p-2 text-slate-500 hover:border-slate-300 hover:text-slate-700" aria-label="Close">\n
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">\n
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />\n
                    </svg>\n
                </button>\n
            </div>\n
            <div id="ajaxModalBody" class="max-h-[75vh] overflow-y-auto p-5"></div>\n
        </div>\n
    </div>\n
</div>\n
    <div id="delete-confirm-modal" class="fixed inset-0 z-[60] hidden" aria-hidden="true">\n
    <div class="absolute inset-0 bg-slate-900/60" data-delete-confirm-backdrop></div>\n
    <div class="relative mx-auto flex min-h-full max-w-2xl items-center justify-center px-4 py-10">\n
        <div class="w-full rounded-2xl bg-white shadow-2xl">\n
            <div class="flex items-start justify-between border-b border-slate-300 px-6 py-5">\n
                <div>\n
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Delete</div>\n
                    <div data-delete-confirm-title class="mt-2 text-lg font-semibold text-slate-900">Delete this item?</div>\n
                </div>\n
                <button type="button" data-delete-confirm-close class="rounded-full border border-slate-300 p-2 text-slate-400 hover:border-slate-300 hover:text-slate-600" aria-label="Close">\n
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">\n
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />\n
                    </svg>\n
                </button>\n
            </div>\n
            <div class="px-6 py-5 text-sm text-slate-600">\n
                <p data-delete-confirm-description class="leading-relaxed">\n
                    This action cannot be undone.\n
                </p>\n
                <div class="mt-5">\n
                    <label class="text-xs uppercase tracking-[0.2em] text-slate-400">\n
                        Type "<span data-delete-confirm-phrase class="font-semibold text-slate-700">DELETE</span>" to confirm\n
                    </label>\n
                    <input\n
                        id="delete-confirm-input"\n
                        type="text"\n
                        autocomplete="off"\n
                        class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 focus:border-rose-300 focus:ring-0"\n
                        placeholder="Type the confirmation text"\n
                    />\n
                    <div data-delete-confirm-hint class="mt-2 text-xs text-slate-400"></div>\n
                </div>\n
            </div>\n
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">\n
                <button type="button" data-delete-confirm-cancel class="rounded-full border border-slate-300 px-5 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300">\n
                    Cancel\n
                </button>\n
                <button type="button" data-delete-confirm-submit class="rounded-full bg-rose-500 px-6 py-2 text-xs font-semibold text-white opacity-50" disabled>\n
                    Delete\n
                </button>\n
            </div>\n
        </div>\n
    </div>\n
</div>\n
\n
<script>\n
    document.addEventListener('DOMContentLoaded', () => {\n
        const modal = document.getElementById('delete-confirm-modal');\n
        if (!modal) {\n
            return;\n
        }\n
\n
        const titleEl = modal.querySelector('[data-delete-confirm-title]');\n
        const descriptionEl = modal.querySelector('[data-delete-confirm-description]');\n
        const phraseEl = modal.querySelector('[data-delete-confirm-phrase]');\n
        const hintEl = modal.querySelector('[data-delete-confirm-hint]');\n
        const inputEl = modal.querySelector('#delete-confirm-input');\n
        const submitBtn = modal.querySelector('[data-delete-confirm-submit]');\n
        const cancelBtn = modal.querySelector('[data-delete-confirm-cancel]');\n
        const closeBtn = modal.querySelector('[data-delete-confirm-close]');\n
        const backdrop = modal.querySelector('[data-delete-confirm-backdrop]');\n
\n
        let activeForm = null;\n
        let requiredPhrase = 'DELETE';\n
\n
        const setButtonState = (enabled) => {\n
            if (!submitBtn) return;\n
            submitBtn.disabled = !enabled;\n
            submitBtn.classList.toggle('opacity-50', !enabled);\n
        };\n
\n
        const openModal = (form) => {\n
            activeForm = form;\n
\n
            const name = (form.getAttribute('data-confirm-name') || '').trim();\n
            requiredPhrase = name !== '' ? name : 'DELETE';\n
            const title = form.getAttribute('data-confirm-title') || (name ? `Delete ${name}?` : 'Delete this item?');\n
            const description = form.getAttribute('data-confirm-description') || 'This action cannot be undone.';\n
            const actionLabel = form.getAttribute('data-confirm-action') || 'Delete';\n
            const hint = form.getAttribute('data-confirm-hint') || '';\n
\n
            if (titleEl) titleEl.textContent = title;\n
            if (descriptionEl) descriptionEl.textContent = description;\n
            if (phraseEl) phraseEl.textContent = requiredPhrase;\n
            if (hintEl) hintEl.textContent = hint;\n
            if (submitBtn) submitBtn.textContent = actionLabel;\n
\n
            if (inputEl) {\n
                inputEl.value = '';\n
                inputEl.placeholder = `Type "${requiredPhrase}" to confirm`;\n
            }\n
\n
            setButtonState(false);\n
            modal.classList.remove('hidden');\n
            modal.setAttribute('aria-hidden', 'false');\n
            document.body.classList.add('overflow-hidden');\n
            setTimeout(() => inputEl?.focus(), 0);\n
        };\n
\n
        const closeModal = () => {\n
            modal.classList.add('hidden');\n
            modal.setAttribute('aria-hidden', 'true');\n
            document.body.classList.remove('overflow-hidden');\n
            activeForm = null;\n
            requiredPhrase = 'DELETE';\n
        };\n
\n
        const handleDeleteRequest = (event, form) => {\n
            if (!form || !form.hasAttribute('data-delete-confirm')) {\n
                return false;\n
            }\n
\n
            event.preventDefault();\n
            event.stopPropagation();\n
            event.stopImmediatePropagation();\n
            openModal(form);\n
            return true;\n
        };\n
\n
        document.addEventListener('click', (event) => {\n
            const trigger = event.target.closest('button[type="submit"], input[type="submit"]');\n
            if (!trigger) {\n
                return;\n
            }\n
\n
            const form = trigger.form || trigger.closest('form');\n
            handleDeleteRequest(event, form);\n
        }, true);\n
\n
        document.addEventListener('submit', (event) => {\n
            const form = event.target;\n
            if (!(form instanceof HTMLFormElement)) {\n
                return;\n
            }\n
\n
            handleDeleteRequest(event, form);\n
        }, true);\n
\n
        inputEl?.addEventListener('input', () => {\n
            const value = (inputEl.value || '').trim();\n
            setButtonState(value === requiredPhrase);\n
        });\n
\n
        inputEl?.addEventListener('keydown', (event) => {\n
            if (event.key !== 'Enter') return;\n
            event.preventDefault();\n
            if ((inputEl.value || '').trim() === requiredPhrase) {\n
                submitBtn?.click();\n
            }\n
        });\n
\n
        submitBtn?.addEventListener('click', () => {\n
            if (!activeForm) return;\n
            const value = (inputEl?.value || '').trim();\n
            if (value !== requiredPhrase) return;\n
            const formToSubmit = activeForm;\n
            closeModal();\n
            formToSubmit.submit();\n
        });\n
\n
        cancelBtn?.addEventListener('click', closeModal);\n
        closeBtn?.addEventListener('click', closeModal);\n
        backdrop?.addEventListener('click', closeModal);\n
\n
        document.addEventListener('keydown', (event) => {\n
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {\n
                closeModal();\n
            }\n
        });\n
    });\n
</script>\n
    <script>\n
    document.addEventListener('DOMContentLoaded', () => {\n
        document.querySelectorAll('table').forEach((table) => {\n
            if (table.closest('[data-table-responsive]')) {\n
                return;\n
            }\n
            const wrapper = document.createElement('div');\n
            wrapper.setAttribute('data-table-responsive', 'true');\n
            wrapper.className = 'overflow-x-auto';\n
            const parent = table.parentElement;\n
            if (parent) {\n
                parent.insertBefore(wrapper, table);\n
                wrapper.appendChild(table);\n
            }\n
        });\n
    });\n
</script>\n
    <div id="pageScriptStack" hidden aria-hidden="true">\n
            </div>\n
</body>\n
</html>\n
' [ASCII](length: 46125) contains "/storage/avatars/customers/1/avatar.png" [ASCII](length: 39).

C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponseAssert.php:45
C:\xampp\htdocs\myapptimatic\vendor\laravel\framework\src\Illuminate\Testing\TestResponse.php:620
C:\xampp\htdocs\myapptimatic\tests\Feature\UserDocumentDisplayTest.php:116

FAILURES!
Tests: 275, Assertions: 899, Failures: 5.


$ php artisan diagnostics:integrity --limit=50

+-------------------------------------------------+-------+--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
| Check                                           | Count | Sample IDs                                                                                                                                                                                                               |
+-------------------------------------------------+-------+--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
| Tasks without project                           | 1     | 21                                                                                                                                                                                                                       |
| Subtasks without task                           | 0     | --                                                                                                                                                                                                                       |
| Project messages without project                | 0     | --                                                                                                                                                                                                                       |
| Task messages without task                      | 0     | --                                                                                                                                                                                                                       |
| Invoices without customer                       | 0     | --                                                                                                                                                                                                                       |
| Invoices with missing project                   | 1     | 14                                                                                                                                                                                                                       |
| Invoices with missing customer record           | 0     | --                                                                                                                                                                                                                       |
| Payroll items without employee                  | 0     | --                                                                                                                                                                                                                       |
| Payroll items without period                    | 0     | --                                                                                                                                                                                                                       |
| Commission payouts without sales rep            | 0     | --                                                                                                                                                                                                                       |
| Commission payouts without earnings             | 3     | 1, 2, 3                                                                                                                                                                                                                  |
| Employee payouts without employee               | 0     | --                                                                                                                                                                                                                       |
| Accounting entries missing references           | 0     | --                                                                                                                                                                                                                       |
| Expense invoices with invalid source type       | 0     | --                                                                                                                                                                                                                       |
| Expense invoices missing expense source         | 0     | --                                                                                                                                                                                                                       |
| Expense invoices missing payroll item           | 0     | --                                                                                                                                                                                                                       |
| Expense invoices missing employee payout        | 0     | --                                                                                                                                                                                                                       |
| Expense invoices missing commission payout      | 0     | --                                                                                                                                                                                                                       |
| Task activities with missing attachments        | 45    | 13, 37, 39, 43, 91, 93, 98, 100, 103, 109, 111, 113, 115, 120, 122, 124, 126, 127, 137, 158, 174, 175, 187, 189, 195, 200, 202, 203, 204, 209, 211, 219, 221, 224, 226, 228, 230, 234, 237, 239, 242, 244, 246, 264, 266 |
| Project chat messages with missing attachments  | 5     | 19, 38, 39, 48, 50                                                                                                                                                                                                       |
| Task chat messages with missing attachments     | 0     | --                                                                                                                                                                                                                       |
| Task subtasks with missing attachments          | 0     | --                                                                                                                                                                                                                       |
| Support ticket replies with missing attachments | 0     | --                                                                                                                                                                                                                       |
| Income entries with missing attachments         | 0     | --                                                                                                                                                                                                                       |
| Expenses with missing attachments               | 0     | --                                                                                                                                                                                                                       |
| Payment proofs with missing attachments         | 0     | --                                                                                                                                                                                                                       |
| Employee payouts with missing payment proofs    | 1     | 3                                                                                                                                                                                                                        |
| Payroll logs with missing payment proofs        | 0     | --                                                                                                                                                                                                                       |
| Projects with missing contract files            | 0     | --                                                                                                                                                                                                                       |
| Projects with missing proposal files            | 1     | 23                                                                                                                                                                                                                       |
| Users with missing avatar files                 | 1     | 17                                                                                                                                                                                                                       |
| Users with missing NID files                    | 0     | --                                                                                                                                                                                                                       |
| Users with missing CV files                     | 0     | --                                                                                                                                                                                                                       |
| Employees with missing photo files              | 5     | 12, 13, 14, 15, 16                                                                                                                                                                                                       |
| Employees with missing NID files                | 0     | --                                                                                                                                                                                                                       |
| Employees with missing CV files                 | 0     | --                                                                                                                                                                                                                       |
| Customers with missing avatar files             | 2     | 9, 11                                                                                                                                                                                                                    |
| Customers with missing NID files                | 0     | --                                                                                                                                                                                                                       |
| Customers with missing CV files                 | 0     | --                                                                                                                                                                                                                       |
| Sales reps with missing avatar files            | 0     | --                                                                                                                                                                                                                       |
| Sales reps with missing NID files               | 0     | --                                                                                                                                                                                                                       |
| Sales reps with missing CV files                | 0     | --                                                                                                                                                                                                                       |
+-------------------------------------------------+-------+--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+


### PHASE 0 Addendum

$ $PSVersionTable.PSVersion

5.1.26100.7705

## PHASE 1 — Remove/Relocate Duplicate Webroot

$ Test-Path public/public
public/public missing

$ Test-Path public.zip
public.zip missing

$ compare public/storage vs storage/app/public
public/storage count=49; storage/app/public count=62
NONE_ONLY_IN_PUBLIC_STORAGE (no files unique to public/storage)

Action:
- Removed non-link directory: public/storage
- Recreated canonical Laravel link: php artisan storage:link

$ cmd /c dir /a public
storage is now <JUNCTION> -> C:\xampp\htdocs\myapptimatic\storage\app\public

Post-check:
- public/public missing
- public.zip missing

## PHASE 2 — Composer Security Remediation (Final)

$ composer update phpunit/phpunit psy/psysh symfony/process --with-all-dependencies
- phpunit/phpunit updated to 10.5.63
- psy/psysh updated to 0.12.20
- symfony/process updated to 7.4.5

$ composer validate --strict
./composer.json is valid

$ composer audit --locked
No security vulnerability advisories found.

## PHASE 3 — npm Security Remediation (Final)

$ npm install
- upgraded vite to 7.3.1 and aligned lockfile

$ npm audit --audit-level=moderate
found 0 vulnerabilities

$ npm run build
vite v7.3.1 build completed successfully

## PHASE 4 — Test Suite Remediation (Final)

Fixed previously failing tests by updating:
- resources/views/client/invoices/pay.blade.php
- app/Http/Controllers/Employee/ProjectTaskController.php
- tests/Feature/EmployeeActivityTrackingTest.php
- resources/views/admin/customers/partials/table.blade.php

$ vendor/bin/phpunit --colors=never
OK (277 tests, 911 assertions)

## PHASE 5 — Integrity Reconciliation (Final)

Implemented safe reconciliation (no record deletion):
- Added table: file_reference_reconciliations
- Added model: App\Models\FileReferenceReconciliation
- Added command: diagnostics:reconcile-missing-files
- Updated diagnostics:integrity to ignore reconciled missing-file references
- Added tests: tests/Feature/DataIntegrityCommandTest.php

$ php artisan diagnostics:reconcile-missing-files
Flagged 60 missing references, nullified 0

$ php artisan diagnostics:integrity --limit=50
All missing-file checks are 0 after reconciliation.

## PHASE 6 — Production Hardening Notes

Updated:
- .env.example => APP_DEBUG=false

Operational note:
- Keep APP_ENV=production and APP_DEBUG=false in production deployment env.
- Local/dev can still use APP_DEBUG=true in local .env when needed.

## PHASE 7 — Cleanup + Verification

$ php artisan storage:link
Link already exists and is valid (public/storage -> storage/app/public)

$ Test-Path public/public
public/public missing

$ Test-Path public.zip
public.zip missing

$ composer audit --locked
No security vulnerability advisories found.

$ npm audit --audit-level=moderate
found 0 vulnerabilities

$ npm run build
success

$ vendor/bin/phpunit --colors=never
OK (277 tests, 911 assertions)

$ php artisan diagnostics:integrity --limit=50
Missing-file checks: all zero
