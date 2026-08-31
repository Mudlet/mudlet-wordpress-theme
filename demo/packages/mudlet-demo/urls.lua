-- Where the world points out ---------------------------------------------------
--
-- Every link that leaves this world, in one place. The world is a parody of
-- mudlet.org and each thing in it opens the real page it is parodying, so these
-- are load-bearing rather than decorative.

local URL = {
    home     = 'https://www.mudlet.org/',
    download = 'https://www.mudlet.org/download/',
    news     = 'https://www.mudlet.org/news/',
    makers   = 'https://www.mudlet.org/the-makers/',
    packages = 'https://packages.mudlet.org/',
    forum    = 'https://forums.mudlet.org/',
    wiki     = 'https://wiki.mudlet.org/',
    discord  = 'https://discord.gg/kuYvMQ9',
    github   = 'https://github.com/Mudlet/Mudlet',
    -- The pages the clerk in the Workshop is reading off a wire. Every line
    -- they say carries one of these, so a visitor who gets the apology instead
    -- of the answer is still one click from the thing itself.
    commits  = 'https://github.com/Mudlet/Mudlet/commits',
    pulls    = 'https://github.com/Mudlet/Mudlet/pulls',
    issues   = 'https://github.com/Mudlet/Mudlet/issues',
    firstish = 'https://github.com/Mudlet/Mudlet/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22',
    ptb      = 'https://make.mudlet.org/snapshots/?platform=all&source=ptb',
    -- The download manager's own links, which is what the real buttons point
    -- at: each one redirects straight to the installer, so opening it puts the
    -- file in the visitor's downloads rather than showing them another page.
    -- The ids belong to whichever release is current and move with each one —
    -- unlike the version, weights and hashes typed into site.lua, which
    -- are the July 2026 snapshot.
    win      = 'https://www.mudlet.org/download/70/',
    macx     = 'https://www.mudlet.org/download/69/',
    macarm   = 'https://www.mudlet.org/download/68/',
    linux    = 'https://www.mudlet.org/download/67/',
    post422  = 'https://www.mudlet.org/2026/07/4-22-mapping-made-friendlier/',
    post4220 = 'https://www.mudlet.org/2026/07/mudlet-4-22-0/',
    post421  = 'https://www.mudlet.org/2026/06/4-21-mudlet-made-better/',
}

return URL
