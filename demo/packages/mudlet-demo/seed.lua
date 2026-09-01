-- Asking the site about itself -------------------------------------------------
--
-- One request, at boot, that replaces what site.lua only guesses at. Nothing
-- here is required: a field that does not arrive leaves the written prose alone.

local D = demo
local SITE = require('mudlet-demo.site')
local people = require('mudlet-demo.people')
local MAKERS, findMaker = people.MAKERS, people.findMaker

-- Asking the site -------------------------------------------------------------
--
-- The endpoint is site-relative on purpose. The demo is framed from the site's
-- own origin — a hard requirement for unrelated reasons, see demo/README.md —
-- so the REST root is reachable from wherever the frame is served without the
-- world having to be told what site it is in. Two spellings of the one route
-- because /wp-json/ only exists with pretty permalinks on; the query form is
-- what a plain install answers, and is tried when the first fails.
local SEED_URLS = {
    '/wp-json/mudlet/v1/demo',
    '/?rest_route=/mudlet/v1/demo',
}

-- The longest the first room will wait for an answer, on top of the 1.5s the
-- connect animation runs anyway. Everywhere there is no WordPress the request
-- fails immediately and none of this is spent.
local SEED_WAIT = 1.5

-- Nothing that arrives is trusted and nothing is required: a field replaces
-- what the world already says only when it turns up with something in it. A
-- site answering half of this — an older theme, a plugin somebody deactivated —
-- leaves the other half as written prose rather than as a hole in a sentence.
local function fill(into, from, keys)
    if type(from) ~= 'table' then return end
    for _, key in ipairs(keys) do
        local value = from[key]
        if value ~= nil and value ~= '' then into[key] = value end
    end
end

-- A notice needs a headline and somewhere to go; the date, the author and the
-- clause under it are decoration and may be missing.
local function notices(posts)
    local clean = {}
    if type(posts) ~= 'table' then return clean end
    for _, post in ipairs(posts) do
        if type(post) == 'table' and type(post.title) == 'string'
            and type(post.url) == 'string' and post.title ~= '' then
            clean[#clean + 1] = {
                date   = tostring(post.date or ''),
                title  = post.title,
                author = tostring(post.author or ''),
                blurb  = tostring(post.blurb or ''),
                url    = post.url,
            }
        end
    end
    return clean
end

-- The ledger, rewritten from the site's copy of it.
--
-- Somebody the hall has never heard of gets a chair; somebody it knows keeps
-- their name, their handle and the nouns the sage answers to, and takes the
-- site's sentence in place of the one written here. Who is on the project now
-- comes across too, so the eight at the front of the ledger are the eight the
-- About dialog currently draws large.
--
-- The exception is an entry marked `own`: a line that talks about this demo
-- from inside it is one the About dialog cannot make, and there is no version
-- of it upstream to take instead.
--
-- Matched on the full name first — the sage's `keys` are deliberately loose,
-- and a loose match is the wrong tool when the question is "is this the same
-- person" rather than "who does this visitor mean".
local function inLedger(name)
    local wanted = name:lower()
    for _, m in ipairs(MAKERS) do
        if m.name:lower() == wanted then return m end
    end
    return findMaker(wanted)
end

local function roster(people)
    if type(people) ~= 'table' then return end
    for _, person in ipairs(people) do
        local name = type(person) == 'table' and person.name or nil
        if type(name) == 'string' and name ~= '' then
            local line = type(person.line) == 'string' and person.line ~= ''
                and person.line or nil
            local gh = type(person.github) == 'string' and person.github ~= ''
                and person.github or nil
            local known = inLedger(name)
            if known then
                if line and not known.own then known.line = line end
                known.gh = known.gh or gh
                if type(person.core) == 'boolean' then known.big = person.core end
            else
                local keys = {}
                for word in name:lower():gmatch('%a+') do keys[#keys + 1] = word end
                if gh then keys[#keys + 1] = gh:lower() end
                MAKERS[#MAKERS + 1] = {
                    big  = person.core ~= false,
                    name = name,
                    gh   = gh,
                    keys = keys,
                    line = line or 'In the credits, and the ledger has not caught up '
                        .. 'with what they have done yet.',
                }
            end
        end
    end
end

function D.applySeed(data)
    if type(data) ~= 'table' then return end

    local release = data.release
    if type(release) == 'table' then
        fill(SITE.release, release, { 'version', 'date', 'date_short', 'date_loud', 'url' })
        if type(release.builds) == 'table' then
            for name, build in pairs(SITE.release.builds) do
                fill(build, release.builds[name], { 'label', 'size', 'short', 'url' })
            end
        end
    end

    fill(SITE.games, data.games, { 'count', 'url' })
    if type(data.games) == 'table' and type(data.games.names) == 'table'
        and #data.games.names > 0 then
        SITE.games.names = data.games.names
    end

    fill(SITE.makers, data.makers, { 'count', 'url' })
    if type(data.makers) == 'table' then roster(data.makers.people) end

    -- Replaced wholesale rather than merged into: what is written in site.lua
    -- is a dozen signatures standing in for the catalogue where there is no
    -- site to ask, not a correction to be laid over the real one.
    fill(SITE.functions, data.functions, { 'count', 'url' })
    if type(data.functions) == 'table' and type(data.functions.list) == 'table' then
        local list, found = {}, 0
        for name, signature in pairs(data.functions.list) do
            if type(name) == 'string' and type(signature) == 'string' then
                list[name] = signature
                found = found + 1
            end
        end
        if found > 0 then SITE.functions.list = list end
    end

    fill(SITE.news, data.news, { 'count', 'url' })
    if type(data.news) == 'table' then
        local board = notices(data.news.posts)
        if #board > 0 then SITE.news.posts = board end
    end
end

-- One request, and one answer either way: whatever happens, D.settled ends up
-- true and the world stops waiting. The handlers filter on the route because
-- they are anonymous and would otherwise hear anything else that ever fetches.
function D.askSite()
    local attempt = 0

    local function settle()
        if D.settled then return end
        D.settled = true
        D.connected()
    end

    local function ask()
        attempt = attempt + 1
        if not SEED_URLS[attempt] then settle() return end
        getHTTP(SEED_URLS[attempt])
    end

    local function ours(url)
        return type(url) == 'string' and url:find('mudlet/v1/demo', 1, true) ~= nil
    end

    registerAnonymousEventHandler('sysGetHttpDone', function(_, url, body)
        if D.settled or not ours(url) then return end
        -- A site that answers with something other than JSON is a site that
        -- does not have this endpoint. That is not an error the visitor needs.
        local ok, data = pcall(yajl.to_value, body)
        if ok then D.applySeed(data) end
        settle()
    end)

    registerAnonymousEventHandler('sysGetHttpError', function(_, _, url)
        if D.settled or not ours(url) then return end
        ask()
    end)

    ask()
end

return { SEED_WAIT = SEED_WAIT }
