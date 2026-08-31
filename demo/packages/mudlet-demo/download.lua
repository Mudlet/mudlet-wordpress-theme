-- The crates and the button ---------------------------------------------------
--
-- What the vault is stacked with and what the orange button on the front page
-- does, which are the same release described twice. Shared by rooms/home.lua,
-- rooms/vault.lua and the `download` verb.

local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local SITE = require('mudlet-demo.site')
local C, say, link = core.C, core.say, core.link

-- What a crate has stencilled on it: "Mudlet 5.0.0 for Windows, 122.7 MiB".
-- One function because that string is printed three times per crate — on the
-- lid, in the link under the description, and in the line take() prints — and
-- three copies of it would drift apart inside one release.
local function crateLabel(name)
    local build = SITE.release.builds[name] or {}
    local label = 'Mudlet ' .. SITE.release.version .. ' for ' .. (build.label or name)
    return build.size and build.size ~= '' and (label .. ', ' .. build.size) or label
end

-- A dozen of the boxed worlds, drawn again every time somebody reads the
-- shelves. The site sends all forty-odd; naming a fixed twelve of them would
-- give the same twelve every look, which is the reason the page's own grid
-- shuffles too. Drawn without replacement, so no lid is stencilled twice.
local function someGames(wanted)
    local pool = {}
    for _, name in ipairs(SITE.games.names) do pool[#pool + 1] = name end
    local shown = {}
    for _ = 1, math.min(wanted, #pool) do
        shown[#shown + 1] = table.remove(pool, math.random(#pool))
    end
    return shown
end

-- The two lines under a crate's description: the hash, elided the way the
-- download page elides it, and the link that is the download itself.
local function crateLines(name)
    local build = SITE.release.builds[name] or {}
    if build.short and build.short ~= '' then
        say(C.dim, '  sha256 ', build.short)
    end
    say(C.dim, '  ', link(crateLabel(name), build.url or SITE.release.url))
end

-- The orange button ----------------------------------------------------------
--
-- The button on the front page is labelled DOWNLOAD MUDLET, so it downloads
-- Mudlet: the build for the machine the visitor is reading this on, the same
-- one the real front page would hand them.
--
-- getOS() is how it knows. In the browser that is not the platform Mudlet was
-- built for — there isn't one — it is the visitor's own OS, sniffed from the
-- user agent, which is the guess the download page makes too, so the world and
-- the page agree without either having to ask the other.
local BUILDS = {
    win    = { url = URL.win,    what = 'the Windows installer' },
    macarm = { url = URL.macarm, what = 'the macOS build', intel = true },
    linux  = { url = URL.linux,  what = 'the Linux AppImage' },
}

local function currentBuild()
    local name, version, third = getOS()
    if name == 'windows' then return BUILDS.win end
    if name == 'linux' then
        -- Android and ChromeOS both come back as linux, with an osType in
        -- front of the processor that nothing else has. Neither of them runs
        -- an AppImage.
        if third == 'android' or third == 'chromeos' then return nil end
        return BUILDS.linux
    end
    if name == 'mac' then
        -- iPhones and iPads say mac as well, and the version is the only thing
        -- here that separates them: the sniffer matches "Mac OS X 10_15_7" and
        -- not an iOS agent's "like Mac OS X", so those arrive unknown.
        if version == 'unknown' then return nil end
        -- Safari reports Intel even on Apple Silicon, so arm64 is the safer
        -- default — the same call the download page makes, with the x86_64
        -- build one line underneath.
        return BUILDS.macarm
    end
    return nil
end

-- `afar` is the visitor typing 'download' in a room the button is not in.
local function press(afar)
    local build = currentBuild()
    if not build then
        openUrl(URL.download)
        say(C.text, 'The button gives, then catches. Whatever you are reading this on is ',
            'not something Mudlet ships an installer for — ',
            link('the download page', URL.download), C.text, ' has every platform there is.')
        return
    end
    openUrl(build.url)
    if afar then
        say(C.text, 'The button is on the front page, but it reaches from here: ',
            build.what, ' starts downloading.')
    else
        say(C.text, 'It gives with a clunk you feel in your wrist, and ', build.what,
            ' starts downloading.')
    end
    say(C.dim, 'If your browser held that back, take it by hand: ',
        link(build.what, build.url), C.dim .. '.')
    if build.intel then
        say(C.dim, 'On an Intel Mac? ', link('The x86_64 build', URL.macx),
            C.dim, ' is the one you want instead.')
    end
end

return {
    crateLabel = crateLabel, crateLines = crateLines, someGames = someGames,
    currentBuild = currentBuild, press = press,
}
