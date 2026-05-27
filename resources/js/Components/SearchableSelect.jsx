import React, { useEffect, useMemo, useRef, useState } from 'react';
import ErrorMessage from './Form/ErrorMessage';

const DEFAULT_DEBOUNCE_MS = 250;

function normalizeOption(option, labelKey, valueKey) {
    if (!option || typeof option !== 'object') {
        return { label: '', value: '' };
    }

    return {
        ...option,
        label: String(option[labelKey] ?? ''),
        value: String(option[valueKey] ?? ''),
    };
}

function normalizeValues(value) {
    if (Array.isArray(value)) {
        return value.map((item) => String(item ?? '')).filter((item) => item !== '');
    }

    const single = String(value ?? '');
    return single ? [single] : [];
}

export default function SearchableSelect({
    name,
    options = [],
    value,
    defaultValue = '',
    onChange,
    onBlur,
    placeholder = 'Select an option',
    searchPlaceholder = 'Search...',
    noResultsLabel = 'No matches found',
    loadingLabel = 'Loading...',
    disabled = false,
    required = false,
    error = null,
    className = '',
    triggerClassName = '',
    panelClassName = '',
    inputClassName = '',
    optionClassName = '',
    labelKey = 'label',
    valueKey = 'value',
    loadOptions = null,
    debounceMs = DEFAULT_DEBOUNCE_MS,
    closeOnSelect = true,
    multiple = false,
    searchable,
    searchableThreshold = 12,
}) {
    const isControlled = value !== undefined;
    const [internalValue, setInternalValue] = useState(String(defaultValue ?? ''));
    const [internalValues, setInternalValues] = useState(normalizeValues(defaultValue));
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');
    const [highlightedIndex, setHighlightedIndex] = useState(-1);
    const [panelPlacement, setPanelPlacement] = useState('bottom');
    const [remoteOptions, setRemoteOptions] = useState([]);
    const [isLoading, setIsLoading] = useState(false);

    const containerRef = useRef(null);
    const triggerRef = useRef(null);
    const panelRef = useRef(null);
    const searchInputRef = useRef(null);
    const requestIdRef = useRef(0);

    const selectedValue = isControlled ? String(value ?? '') : internalValue;
    const selectedValues = isControlled ? normalizeValues(value) : internalValues;

    const normalizedOptions = useMemo(() => {
        return (Array.isArray(options) ? options : []).map((option) => normalizeOption(option, labelKey, valueKey));
    }, [options, labelKey, valueKey]);

    const isSearchEnabled = useMemo(() => {
        if (typeof searchable === 'boolean') {
            return searchable;
        }

        if (typeof loadOptions === 'function') {
            return true;
        }

        return normalizedOptions.length > Number(searchableThreshold || 12);
    }, [searchable, loadOptions, normalizedOptions.length, searchableThreshold]);

    useEffect(() => {
        if (!isControlled) {
            setInternalValue(String(defaultValue ?? ''));
            setInternalValues(normalizeValues(defaultValue));
        }
    }, [defaultValue, isControlled]);

    useEffect(() => {
        const delay = Number.isFinite(Number(debounceMs)) ? Number(debounceMs) : DEFAULT_DEBOUNCE_MS;
        const timer = window.setTimeout(() => {
            setDebouncedSearchTerm(searchTerm.trim());
        }, delay);

        return () => window.clearTimeout(timer);
    }, [searchTerm, debounceMs]);

    useEffect(() => {
        if (!isOpen) {
            return undefined;
        }

        const handleClickOutside = (event) => {
            if (!containerRef.current?.contains(event.target)) {
                setIsOpen(false);
                setHighlightedIndex(-1);
            }
        };

        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                setIsOpen(false);
                setHighlightedIndex(-1);
                triggerRef.current?.focus();
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleEscape);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen || typeof loadOptions !== 'function') {
            return undefined;
        }

        const currentRequestId = ++requestIdRef.current;
        setIsLoading(true);

        Promise.resolve(loadOptions(debouncedSearchTerm))
            .then((nextOptions) => {
                if (currentRequestId !== requestIdRef.current) {
                    return;
                }

                const normalized = (Array.isArray(nextOptions) ? nextOptions : []).map((option) =>
                    normalizeOption(option, labelKey, valueKey),
                );

                setRemoteOptions(normalized);
            })
            .catch(() => {
                if (currentRequestId === requestIdRef.current) {
                    setRemoteOptions([]);
                }
            })
            .finally(() => {
                if (currentRequestId === requestIdRef.current) {
                    setIsLoading(false);
                }
            });

        return undefined;
    }, [isOpen, loadOptions, debouncedSearchTerm, labelKey, valueKey]);

    useEffect(() => {
        if (!isOpen) {
            return undefined;
        }

        const updatePlacement = () => {
            const triggerRect = triggerRef.current?.getBoundingClientRect();
            if (!triggerRect) {
                return;
            }

            const panelHeight = Math.min(260, panelRef.current?.offsetHeight || 220);
            const viewportHeight = window.innerHeight;
            const roomBelow = viewportHeight - triggerRect.bottom;
            const roomAbove = triggerRect.top;

            setPanelPlacement(roomBelow >= panelHeight || roomBelow >= roomAbove ? 'bottom' : 'top');
        };

        updatePlacement();
        window.addEventListener('resize', updatePlacement);
        window.addEventListener('scroll', updatePlacement, true);

        return () => {
            window.removeEventListener('resize', updatePlacement);
            window.removeEventListener('scroll', updatePlacement, true);
        };
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        if (!isSearchEnabled) {
            return;
        }

        searchInputRef.current?.focus();
    }, [isOpen, isSearchEnabled]);

    const filteredOptions = useMemo(() => {
        const sourceOptions = typeof loadOptions === 'function' ? remoteOptions : normalizedOptions;

        if (!isSearchEnabled) {
            return sourceOptions;
        }

        const query = debouncedSearchTerm.toLowerCase();

        if (!query) {
            return sourceOptions;
        }

        return sourceOptions.filter((option) => option.label.toLowerCase().includes(query));
    }, [normalizedOptions, remoteOptions, debouncedSearchTerm, loadOptions, isSearchEnabled]);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        setHighlightedIndex(filteredOptions.length > 0 ? 0 : -1);
    }, [isOpen, filteredOptions.length]);

    const selectedOption = useMemo(() => {
        const mergedOptions = typeof loadOptions === 'function' ? [...remoteOptions, ...normalizedOptions] : normalizedOptions;
        const match = mergedOptions.find((option) => option.value === selectedValue);

        if (match) {
            return match;
        }

        if (selectedValue) {
            return { label: selectedValue, value: selectedValue };
        }

        return null;
    }, [normalizedOptions, remoteOptions, loadOptions, selectedValue]);

    const selectedOptions = useMemo(() => {
        const mergedOptions = typeof loadOptions === 'function' ? [...remoteOptions, ...normalizedOptions] : normalizedOptions;
        return selectedValues.map((entry) => {
            const match = mergedOptions.find((option) => option.value === entry);
            return match || { label: entry, value: entry };
        });
    }, [normalizedOptions, remoteOptions, loadOptions, selectedValues]);

    const applySelection = (option) => {
        const nextValue = String(option?.value ?? '');

        if (multiple) {
            const exists = selectedValues.includes(nextValue);
            const nextValues = exists
                ? selectedValues.filter((entry) => entry !== nextValue)
                : [...selectedValues, nextValue];

            if (!isControlled) {
                setInternalValues(nextValues);
            }

            if (typeof onChange === 'function') {
                onChange(nextValues, option ?? null);
            }

            if (closeOnSelect) {
                setIsOpen(false);
                setSearchTerm('');
                setDebouncedSearchTerm('');
                setHighlightedIndex(-1);
                triggerRef.current?.focus();
            }

            return;
        }

        if (!isControlled) {
            setInternalValue(nextValue);
        }

        if (typeof onChange === 'function') {
            onChange(nextValue, option ?? null);
        }

        if (closeOnSelect) {
            setIsOpen(false);
            setSearchTerm('');
            setDebouncedSearchTerm('');
            setHighlightedIndex(-1);
            triggerRef.current?.focus();
        }
    };

    const handleTriggerKeyDown = (event) => {
        if (disabled) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            setIsOpen(true);
        }
    };

    const handleSearchKeyDown = (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlightedIndex((prev) => Math.min(prev + 1, filteredOptions.length - 1));
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlightedIndex((prev) => Math.max(prev - 1, 0));
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            if (highlightedIndex >= 0 && highlightedIndex < filteredOptions.length) {
                applySelection(filteredOptions[highlightedIndex]);
            }
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setIsOpen(false);
            setHighlightedIndex(-1);
            triggerRef.current?.focus();
        }
    };

    return (
        <div ref={containerRef} className={`relative ${className}`.trim()}>
            {multiple ? (
                selectedValues.map((entry, index) => (
                    <input key={`${entry}-${index}`} type="hidden" name={name} value={entry} />
                ))
            ) : (
                <input type="hidden" name={name} value={selectedValue} required={required} />
            )}

            <button
                ref={triggerRef}
                type="button"
                aria-haspopup="listbox"
                aria-expanded={isOpen}
                disabled={disabled}
                className={[
                    'flex h-8 w-full items-center justify-between rounded-full border border-slate-300 bg-white px-4 py-1.5 text-left text-xs text-slate-700 transition focus:outline-none focus:ring-1 focus:ring-teal-600',
                    disabled ? 'cursor-not-allowed bg-slate-100 text-slate-400' : 'hover:border-teal-300',
                    error ? 'border-rose-500 focus:ring-rose-500' : '',
                    triggerClassName,
                ]
                    .filter(Boolean)
                    .join(' ')}
                onClick={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    setIsOpen((prev) => !prev);
                }}
                onKeyDown={handleTriggerKeyDown}
                onBlur={onBlur}
            >
                <span className="block flex-1 truncate text-left">
                    {multiple
                        ? (selectedOptions.length > 0
                            ? (selectedOptions.length <= 2
                                ? selectedOptions.map((option) => option.label).join(', ')
                                : `${selectedOptions.length} selected`)
                            : placeholder)
                        : (selectedOption?.label || placeholder)}
                </span>
                <svg className="h-3.5 w-3.5 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        fillRule="evenodd"
                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.176l3.71-3.947a.75.75 0 111.08 1.04l-4.25 4.518a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z"
                        clipRule="evenodd"
                    />
                </svg>
            </button>

            {isOpen ? (
                <div
                    ref={panelRef}
                    className={[
                        'absolute z-40 w-full rounded-2xl border border-slate-200 bg-white shadow-lg',
                        panelPlacement === 'bottom' ? 'top-full mt-1' : 'bottom-full mb-1',
                        panelClassName,
                    ]
                        .filter(Boolean)
                        .join(' ')}
                >
                    {isSearchEnabled ? (
                        <div className="border-b border-slate-100 p-2">
                            <input
                                ref={searchInputRef}
                                type="text"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                onKeyDown={handleSearchKeyDown}
                                placeholder={searchPlaceholder}
                                className={[
                                    'h-8 w-full rounded-full border border-slate-300 px-4 py-1.5 text-xs text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-600',
                                    inputClassName,
                                ]
                                    .filter(Boolean)
                                    .join(' ')}
                            />
                        </div>
                    ) : null}

                    <ul role="listbox" className="max-h-56 overflow-y-auto py-1">
                        {isLoading ? (
                            <li className="px-3 py-1.5 text-xs text-slate-500">{loadingLabel}</li>
                        ) : filteredOptions.length > 0 ? (
                            filteredOptions.map((option, index) => {
                                const isActive = index === highlightedIndex;
                                const isSelected = multiple
                                    ? selectedValues.includes(option.value)
                                    : option.value === selectedValue;

                                return (
                                    <li key={`${option.value}-${index}`} role="option" aria-selected={isSelected}>
                                        <button
                                            type="button"
                                            className={[
                                                'w-full px-3 py-1.5 text-left text-xs text-slate-700 transition',
                                                isActive && !isSelected ? 'bg-slate-100 text-slate-900' : '',
                                                isSelected
                                                    ? 'bg-teal-50 font-semibold text-teal-800 hover:bg-teal-50 hover:text-teal-800'
                                                    : 'hover:bg-slate-100 hover:text-slate-900',
                                                optionClassName,
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            onMouseEnter={() => setHighlightedIndex(index)}
                                            onMouseDown={(event) => {
                                                event.preventDefault();
                                            }}
                                            onClick={(event) => {
                                                event.preventDefault();
                                                applySelection(option);
                                            }}
                                        >
                                            {option.label}
                                        </button>
                                    </li>
                                );
                            })
                        ) : (
                            <li className="px-3 py-1.5 text-xs text-slate-500">{noResultsLabel}</li>
                        )}
                    </ul>
                </div>
            ) : null}

            <ErrorMessage message={error} />
        </div>
    );
}
