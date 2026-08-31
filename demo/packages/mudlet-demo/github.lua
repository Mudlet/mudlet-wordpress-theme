-- The clerk's book -------------------------------------------------------------
--
-- The one room whose contents are fetched rather than written, and the only
-- place in this package that talks to anything but the site it is framed in.
-- The room itself is in rooms/workshop.lua.

local D = demo
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local people = require('mudlet-demo.people')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd
local spell, spellCap = core.spell, core.spellCap
local findMaker, ulen = people.findMaker, people.ulen

-- The Workshop ----------------------------------------------------------------
--
-- One room in this world does not know what it says until somebody asks.
--
-- Everywhere else the facts arrive with the seed: one request, at boot, and the
-- prose is settled before the visitor has read a word of it. What landed this
-- week cannot work that way and should not — it is not a fact about mudlet.org
-- at all, it is a fact about the repository, and it is already out of date by
-- the time the page has finished loading. So the clerk asks GitHub, from the
-- visitor's own browser, at the moment the question is put.
--
-- That is possible because api.github.com allows any origin and wants no token
-- for either of these routes, and because mudlet-web falls back to its proxy
-- for any origin that refuses a direct fetch. It costs the visitor sixty
-- requests an hour on the commits route and ten a minute on the search — a
-- budget nobody can spend by hand, and the one who tries is told so in as many
-- words rather than shown an error.
--
-- Nothing here is required. Every failure has a line in the clerk's own voice,
-- every one of those lines carries the link to the page it could not read, and
-- the room reads the same whether GitHub answered or not.

local GH = {
    -- Commits on the default branch in the last seven days. per_page=100 is
    -- the ceiling, and a week that beats it is reported as "a hundred and
    -- more": a second page would be another request for a number nobody is
    -- checking against anything.
    commits = 'https://api.github.com/repos/Mudlet/Mudlet/commits?per_page=100&since=',
    -- Counting open issues without counting pull requests takes the search
    -- API — the repo endpoint's open_issues_count adds the two together, and a
    -- clerk who says six hundred when five hundred are issues is worse than a
    -- clerk who says nothing at all.
    issues  = 'https://api.github.com/search/issues?per_page=1'
        .. '&q=repo%3AMudlet%2FMudlet+is%3Aissue+is%3Aopen',
    first   = 'https://api.github.com/search/issues?per_page=1'
        .. '&q=repo%3AMudlet%2FMudlet+is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22',
}

-- Long enough for a cold lookup on a phone, short enough that nobody decides
-- the room is broken. The browser's fetch has no timeout of its own, so a
-- request left hanging would otherwise leave the clerk mid-sentence for good.
local FETCH_WAIT = 8

-- An answer is kept for five minutes. Asking twice is a thing visitors do to a
-- live number — looking again is how you check that it is one — and the second
-- ask should cost the room nothing.
local FRESH = 300

local function settleJob(reason, body)
    local job = D.job
    if not job then return end
    D.job = nil
    if job.timer then killTimer(job.timer) end
    job.done(reason, body)
end

local function bindHTTP()
    if D.httpBound then return end
    D.httpBound = true
    -- Anonymous handlers hear every request the profile makes, the seed's
    -- included, so both ends filter: these on the url in flight, the seed's on
    -- its own route.
    registerAnonymousEventHandler('sysGetHttpDone', function(_, url, body)
        if D.job and url == D.job.url then settleJob(nil, body) end
    end)
    registerAnonymousEventHandler('sysGetHttpError', function(_, message, url)
        if not (D.job and url == D.job.url) then return end
        -- Both of GitHub's rate limits answer 403, and the secondary one
        -- sometimes 429. Everything else — no route, no network, a proxy
        -- having a bad afternoon — is one thing from where the visitor is
        -- standing, and gets the one other line.
        local code = tostring(message or ''):match('HTTP (%d+)')
        settleJob((code == '403' or code == '429') and 'limit' or 'wire')
    end)
end

-- One request in flight. A second question asked while the first is still out
-- replaces it: the old answer is dropped rather than printed late, underneath
-- a line it no longer belongs to.
local function fetch(url, done)
    bindHTTP()
    if D.job and D.job.timer then killTimer(D.job.timer) end
    local job = { url = url, done = done }
    D.job = job
    job.timer = tempTimer(FETCH_WAIT, function()
        if D.job == job then settleJob('wire') end
    end)
    getHTTP(url, { Accept = 'application/vnd.github+json' })
end

local function decode(body)
    local ok, data = pcall(yajl.to_value, body)
    if ok and type(data) == 'table' then return data end
end

-- An answer that lands after the visitor has walked out belongs to a room they
-- are not in. The sage's greeting checks the same thing for the same reason: a
-- clerk should not call after you from another room.
local function stillHere()
    return D.here == 'workshop'
end

-- What the clerk says when GitHub does not answer. Two lines, because the two
-- reasons are genuinely different and one of them is the visitor's own doing:
-- an unauthenticated caller gets sixty requests an hour per address, and a
-- browser that has been sitting on this page all afternoon can spend them.
local function apology(reason, label, url)
    if reason == 'limit' then
        say(C.say, '"That is my lot."', C.text, ' The clerk sets the pen down, ',
            'unembarrassed. ', C.say, '"They count how much I am allowed to say, and I ',
            'have said all of it this hour. Come back when it has forgotten me — or read ',
            'it off the wall yourself, it is the same wall."')
    else
        say(C.say, '"Not written up."', C.text, ' The clerk shuts the book on a thumb. ',
            C.say, '"The wire between here and the workshop proper is down. It happens. ',
            'It is all posted outside, if you cannot wait for me."')
    end
    say(C.dim, '  ', link(label, url))
end

-- GitHub flags its own bots and not the ones a project keeps: dependabot
-- arrives typed Bot, and mudlet-machine-account — which pushes the translation
-- and release commits — arrives as an ordinary user with a machine's name. The
-- clerk counts hands and machines apart because the difference is the
-- interesting half of the number.
local function isMachine(who, kind)
    return kind == 'Bot' or who:find('%[bot%]') ~= nil
        or who:find('machine%-account') ~= nil
end

-- Cut to n code points, not n bytes: commit messages carry umlauts like
-- everything else here, and a byte cut lands in the middle of one.
local function clip(str, n)
    if ulen(str) <= n then return str end
    local count = 0
    for i = 1, #str do
        local byte = str:byte(i)
        if byte < 128 or byte > 191 then
            count = count + 1
            if count > n - 1 then return str:sub(1, i - 1) .. '…' end
        end
    end
    return str
end

-- "2026-08-31T03:39:17Z" -> "three hours ago".
--
-- os.time reads a table as *local* time and the API answers in UTC, so the
-- difference between the two clocks is added back on. Without it the clerk is
-- wrong by the visitor's own offset, which is worst in exactly the places
-- nobody testing this lives.
--
-- Both tables have their isdst dropped, which is the whole trick: os.date
-- fills it in from the *current* season, and a table that says "not summer
-- time" put through os.time in July is an hour out. Absent, it is worked out
-- from the date in hand — which is the right answer for both of these.
local function ago(iso)
    local y, mo, d, h, mi, s = tostring(iso):match('(%d+)-(%d+)-(%d+)T(%d+):(%d+):(%d+)')
    if not y then return nil end
    local at = os.time({ year = tonumber(y), month = tonumber(mo), day = tonumber(d),
        hour = tonumber(h), min = tonumber(mi), sec = tonumber(s) })
    local utc = os.date('!*t')
    utc.isdst = nil
    local skew = os.difftime(os.time(), os.time(utc))
    local secs = os.difftime(os.time(), at + skew)
    if secs < 90 then return 'just now' end
    if secs < 5400 then return spell(math.floor(secs / 60 + 0.5)) .. ' minutes ago' end
    if secs < 129600 then return spell(math.floor(secs / 3600 + 0.5)) .. ' hours ago' end
    return spell(math.floor(secs / 86400 + 0.5)) .. ' days ago'
end

-- The clerk's two answers, printed. Neither prints the sentence that introduces
-- it: that line belongs to the ask, which knows whether the clerk had to go and
-- look or was still holding the number.
local function tellWeek(week)
    if week.count == 0 then
        say(C.say, '"Nothing in seven days."', C.text, ' Neither worried nor surprised. ',
            C.say, '"It goes in bursts. Somebody will break something tonight."')
    else
        local hands = spell(week.people) .. (week.people == 1 and ' hand' or ' hands')
        local machines = spell(week.machines)
            .. (week.machines == 1 and ' machine' or ' machines')
        -- A week with nothing but machines in it is a real week — the release
        -- and translation robots push on their own — and "from no hands" is
        -- not a sentence anybody says out loud.
        local from = 'from ' .. hands
        if week.people == 0 then
            from = 'and every one of them from a machine'
        elseif week.machines > 0 then
            from = from .. ' and ' .. machines
        end
        say(C.say, '"', week.more and 'A hundred and more' or spellCap(week.count),
            ' in seven days,"', C.text, ' the clerk says, ', C.say, '"', from, '."')
        if week.last then
            say(C.dim, '  ', week.last.when and (week.last.when .. ' — ') or '',
                link(week.last.title, week.last.url, week.last.title), C.dim,
                ', by ', week.last.who)
        end
    end
    say(C.dim, '  ', link('the full run of it', URL.commits))
end

local function tellIssues(open)
    if open.first and open.first > 0 then
        say(C.say, '"', tostring(open.count), ' open,"', C.text, ' the clerk says, ',
            C.say, '"and ', spell(open.first), ' of them marked for anyone who fancies a ',
            'first go at it."')
        say(C.dim, '  ', link('the ' .. spell(open.first) .. ' of them', URL.firstish))
    elseif open.first then
        say(C.say, '"', tostring(open.count), ' open, and not one of them marked for a first ',
            'go."', C.text, ' The clerk sounds mildly impressed. ', C.say, '"Somebody has ',
            'been taking them."')
    else
        say(C.say, '"', tostring(open.count), ' open."', C.text, ' Said the way you would say ',
            'the tide is in.')
    end
    say(C.dim, '  ', link('the board itself', URL.issues))
end

-- What landed in the last seven days: one request, and everything the clerk
-- says about it is counted out of the answer rather than read off a field.
function D.week()
    say()
    if D.weekly and os.difftime(os.time(), D.weekly.at) < FRESH then
        say(C.text, 'The clerk does not need to look twice.')
        tellWeek(D.weekly)
        return
    end

    local ok, since = pcall(os.date, '!%Y-%m-%dT%H:%M:%SZ', os.time() - 7 * 86400)
    if not ok or type(since) ~= 'string' then
        apology('wire', 'the full run of it', URL.commits)
        return
    end

    say(C.text, 'The clerk pulls the week down off the wall by the window.')
    fetch(GH.commits .. since, function(reason, body)
        if not stillHere() then return end
        local list = not reason and decode(body) or nil
        if not list then
            apology(reason or 'wire', 'the full run of it', URL.commits)
            return
        end

        local week = { at = os.time(), count = #list, more = #list >= 100,
            people = 0, machines = 0 }
        local seen = {}
        for _, entry in ipairs(list) do
            local account = type(entry.author) == 'table' and entry.author or nil
            local committed = type(entry.commit) == 'table' and entry.commit or {}
            local authored = type(committed.author) == 'table' and committed.author or {}
            -- The login where GitHub matched the email to an account, and the
            -- name off the commit itself where it did not. Either way it is
            -- the same person twice under one key.
            local who = account and account.login or authored.name or '?'
            if not seen[who] then
                seen[who] = true
                if isMachine(tostring(who):lower(), account and account.type) then
                    week.machines = week.machines + 1
                else
                    week.people = week.people + 1
                end
            end
            if not week.last and type(committed.message) == 'string' then
                -- os.date('!*t') is the one call in this file that assumes a
                -- full os library under the wasm; the clause it feeds is
                -- decoration, so it is asked for rather than relied on.
                local timed, when = pcall(ago, authored.date)
                week.last = {
                    title = clip(committed.message:match('^[^\r\n]*') or '', 56),
                    who   = tostring(who),
                    when  = timed and type(when) == 'string' and when or nil,
                    url   = type(entry.html_url) == 'string' and entry.html_url or URL.commits,
                }
            end
        end

        D.weekly = week
        tellWeek(week)
    end)
end

-- What is still open: two requests, chained, because the search API counts one
-- query at a time. The second one — the issues a stranger could take — is the
-- reason this room is worth walking into, but it is not worth losing the first
-- number over, so a failure there answers with what came back.
function D.issues()
    say()
    if D.open and os.difftime(os.time(), D.open.at) < FRESH then
        say(C.text, 'The clerk points at the board without turning round.')
        tellIssues(D.open)
        return
    end

    say(C.text, 'The clerk runs a thumb down the board.')
    fetch(GH.issues, function(reason, body)
        if not stillHere() then return end
        local data = not reason and decode(body) or nil
        local count = data and tonumber(data.total_count)
        if not count then
            apology(reason or 'wire', 'the board itself', URL.issues)
            return
        end
        fetch(GH.first, function(reason2, body2)
            if not stillHere() then return end
            local firsts = not reason2 and decode(body2) or nil
            D.open = { at = os.time(), count = count,
                first = firsts and tonumber(firsts.total_count) or nil }
            tellIssues(D.open)
        end)
    end)
end

-- The clerk answers to two subjects and admits to the rest. The nouns are
-- matched on a substring the way the room's things are, so "this week",
-- "commits" and "what landed" all reach the same book.
local function about(noun, words)
    for _, word in ipairs(words) do
        if noun:find(word, 1, true) then return true end
    end
end

function D.askClerk(noun)
    if noun == '' or noun == 'clerk' then
        say()
        say(C.text, 'The clerk looks up, pen still moving.')
        say(C.say, '"Two things I keep: what has landed this week, and what is still open."')
        say(C.dim, '  ', cmd('ask about this week', 'ask about this week',
                'the last seven days, off github.com', C.dim),
            C.dim, '   ·   ', cmd('ask about issues', 'ask about issues',
                'what is still open, off github.com', C.dim))
        return
    end
    if about(noun, { 'week', 'commit', 'land', 'chang', 'new', 'recent' }) then D.week() return end
    if about(noun, { 'issue', 'bug', 'open', 'board', 'first', 'todo', 'help' }) then D.issues() return end

    say()
    if findMaker(noun) then
        say(C.say, '"Names are the sage\'s book, not mine,"', C.text, ' the clerk says, ',
            'nodding through the wall. ', C.say, '"Two doors round, past the arguing."')
    else
        say(C.say, '"Not in my book."', C.text, ' The clerk shrugs, not unkindly.')
    end
    say(C.dim, 'What the clerk does keep: ',
        cmd('this week', 'ask about this week', 'what has landed', C.dim), C.dim, ', and ',
        cmd('what is open', 'ask about issues', 'what is still open', C.dim), C.dim, '.')
end
