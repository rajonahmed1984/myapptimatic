import React, { useRef, useState } from 'react';
import { router } from '@inertiajs/react';

const PULL_THRESHOLD = 70;
const MAX_PULL = 100;
const DRAG_RATIO = 0.5;

/**
 * A native-feeling pull-to-refresh gesture for the mobile content area only
 * (desktop is untouched via md:hidden on the indicator). Wrapping this once
 * around MobileAppShell's <main> gives every page in every portal the
 * gesture for free, using the same Inertia reload every "Reset"/"Refresh"
 * link on these pages already triggers.
 */
export default function PullToRefresh({ children, onRefresh, className = '' }) {
    const [distance, setDistance] = useState(0);
    const [refreshing, setRefreshing] = useState(false);
    const startY = useRef(0);
    const dragging = useRef(false);

    const atTop = () => (typeof window === 'undefined' ? true : window.scrollY <= 0);

    const handleTouchStart = (event) => {
        if (refreshing || !atTop() || event.touches.length !== 1) {
            return;
        }
        dragging.current = true;
        startY.current = event.touches[0].clientY;
    };

    const handleTouchMove = (event) => {
        if (!dragging.current) {
            return;
        }
        const delta = event.touches[0].clientY - startY.current;
        if (delta <= 0 || !atTop()) {
            dragging.current = false;
            setDistance(0);
            return;
        }
        setDistance(Math.min(delta * DRAG_RATIO, MAX_PULL));
    };

    const finish = () => {
        setRefreshing(false);
        setDistance(0);
    };

    const handleTouchEnd = () => {
        if (!dragging.current) {
            return;
        }
        dragging.current = false;

        if (distance < PULL_THRESHOLD) {
            setDistance(0);
            return;
        }

        setRefreshing(true);
        setDistance(PULL_THRESHOLD);

        if (typeof onRefresh === 'function') {
            Promise.resolve(onRefresh()).finally(finish);
        } else {
            router.reload({ preserveScroll: true, preserveState: true, onFinish: finish });
        }
    };

    const active = refreshing || distance > 0;

    return (
        <div className={className} onTouchStart={handleTouchStart} onTouchMove={handleTouchMove} onTouchEnd={handleTouchEnd}>
            <div
                className="flex items-center justify-center overflow-hidden md:hidden"
                style={{ height: active ? distance : 0, transition: dragging.current ? 'none' : 'height 200ms ease' }}
                aria-hidden={!active}
            >
                <div
                    className={`h-6 w-6 rounded-full border-2 border-teal-500 border-t-transparent ${refreshing || distance >= PULL_THRESHOLD ? 'animate-spin' : ''}`}
                    style={!refreshing ? { transform: `rotate(${distance * 3}deg)` } : undefined}
                />
            </div>
            {children}
        </div>
    );
}
