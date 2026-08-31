import { useEffect, useRef } from 'react';
import type { LandingProps } from '@mudlet/mudlet-web';

/** How many demo profiles may exist. Slots are claimed by lock and released
 *  when a tab closes, so this is a ceiling on *concurrent* tabs, not on
 *  visits — the ninth simultaneous tab is the one that gets an ad-hoc name. */
const SLOTS = 8;

/**
 * A Mudlet Web profile is single-owner across tabs: a second tab opening the
 * same one gets a "this is open in another tab" screen instead of a session.
 * A homepage can't have that, so each tab takes a profile of its own
 * (`profileMode: 'perLogin'`, where the account name selects the profile).
 *
 * The names come from a fixed pool held by Web Locks rather than being unique
 * per tab: a lock is released the moment its tab goes away, so the next tab
 * reuses that slot's profile instead of leaving another one behind in
 * IndexedDB for good — there is no API to delete them.
 */
function claimSlot(name: string): Promise<boolean> {
    return new Promise(resolve => {
        navigator.locks
            .request(name, { ifAvailable: true }, lock => {
                resolve(lock !== null);
                // Held, never resolved: the lock lasts as long as the tab does.
                return lock ? new Promise<void>(() => {}) : undefined;
            })
            .catch(() => resolve(false));
    });
}

async function claimProfileName(): Promise<string> {
    const adHoc = () => `demo ${Math.random().toString(36).slice(2, 8)}`;
    if (!navigator.locks) return adHoc();
    for (let i = 1; i <= SLOTS; i++) {
        if (await claimSlot(`mudlet-demo-slot-${i}`)) return `demo ${i}`;
    }
    return adHoc();
}

/**
 * There is no landing screen in the embed: a profile is claimed and opened
 * **offline** the moment the app mounts, so a visitor lands straight in a
 * session. `openProfile(id, false)` is the "Open offline" path of the stock
 * BrandLoginScreen — nothing dials, and the brand's `mud` target (required to
 * enter branded mode at all) is never used.
 */
export function AutoLanding({ openProfile, ensureBrandProfile }: LandingProps) {
    const opened = useRef(false);

    useEffect(() => {
        if (opened.current) return;
        opened.current = true;
        let live = true;
        claimProfileName().then(account => {
            if (live) openProfile(ensureBrandProfile(account), false);
        });
        return () => { live = false; };
    }, [openProfile, ensureBrandProfile]);

    return <div className="demo-boot" />;
}
