-- The imp's two lists ----------------------------------------------------------
--
-- The Stacks, and the one thing in this world the visitor writes an alias for.
-- The room itself is in rooms/stacks.lua.
--
-- The Workshop demonstrates a trigger: the client reacting to what the game
-- says. This is the other direction — the client reshaping what the visitor
-- says — and the two of them together are the whole of the claim the front page
-- makes two sections down. They have to be two rooms. An alias only ever acts
-- on input, so a trickster who says one thing and means another is a trigger
-- wearing a hat, and the honest version of that gag is this: the imp never
-- hears what you typed, only what your client sent, and after an alias those
-- are different sentences.
--
-- Nothing here is a list of function names. There are two lists and neither is
-- written down in this repository:
--
--   the catalogue   Mudlet's own src/lua-function-list.json — every documented
--                   name and the signature the editor's autocomplete shows for
--                   it — arriving with the seed. See inc/demo-seed.php.
--   the shelves     what this client actually has: _G, counted here, in the
--                   runtime the visitor is standing in.
--
-- The difference between them is not decoration either. A handful of names in
-- the catalogue are not in this build (three of them are about map
-- perspective), and everything Lua itself brought is on the shelf without being
-- in the catalogue. The imp answers out of both, so all four of those cases are
-- derived rather than written, and the room reads correctly against a client
-- nobody has told it about.

local D = demo
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local SITE = require('mudlet-demo.site')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd
local spell, spellCap, thousands = core.spell, core.spellCap, core.thousands

-- The shelves ------------------------------------------------------------------

-- What this client can be told to do, counted rather than claimed. _G is the
-- whole of it: Mudlet's API arrives as globals, which is why every call in this
-- package is unqualified.
--
-- All three caches below are filled on first use, which is the first time
-- somebody walks in — long after the seed has landed and long after the client
-- has finished installing itself. A number that changed while somebody was
-- reading the room would be worse than one that is a minute old.
local shelved
local function shelves()
    if shelved then return shelved end
    shelved = 0
    for _, value in pairs(_G) do
        if type(value) == 'function' then shelved = shelved + 1 end
    end
    return shelved
end

-- table.concat and os.date are on the shelf too, one level down. Resolved
-- rather than refused: a visitor who types a dotted name has understood
-- something about Lua and should not be told they have not.
local function resolve(name)
    local at = _G
    for part in name:gmatch('[^%.]+') do
        if type(at) ~= 'table' then return nil end
        at = at[part]
    end
    return at
end

local function onShelf(name)
    return type(resolve(name)) == 'function'
end

-- The catalogue ----------------------------------------------------------------

local function catalogue()
    local list = SITE.functions.list
    return type(list) == 'table' and list or {}
end

local function signature(name)
    return catalogue()[name]
end

-- Names in the catalogue that this build does not have. Four of them, at the
-- time of writing, which is a fact about a client rather than a fact anybody
-- typed — so it is counted here every time instead.
local absent
local function missing()
    if absent then return absent end
    absent = {}
    for name in pairs(catalogue()) do
        if not onShelf(name) then absent[#absent + 1] = name end
    end
    table.sort(absent)
    return absent
end

-- Everything the imp will answer to, folded, so `enabletrigger` can be met with
-- the true spelling rather than with a shrug. The catch-all alias lowercases
-- what the visitor typed before the world ever sees it — see D.input — so the
-- fetch verb hands us the raw line and this is what makes the difference
-- survivable.
local folded
local function similar(name)
    if not folded then
        folded = {}
        for real in pairs(catalogue()) do folded[real:lower()] = real end
        for real, value in pairs(_G) do
            if type(value) == 'function' and type(real) == 'string' then
                folded[real:lower()] = folded[real:lower()] or real
            end
        end
    end
    return folded[name:lower()]
end

-- The manual has an anchor per documented name and nothing for the rest, so a
-- box nobody wrote up links to the index instead of to a fragment that is not
-- there.
local function manual(name)
    if signature(name) then
        return link(name, URL.functions .. '#' .. name, 'the manual on ' .. name)
    end
    return link('the manual', URL.functions)
end

-- The bet ----------------------------------------------------------------------
--
-- Three true names in a row, exactly, no fumbles. It is a real count of real
-- attempts: the imp is not pretending to keep score.
local BET = 3

-- The three it asks for. Long, real, and picked because they are hateful to
-- type — the longest name in the catalogue is the first of them.
local ASKS = {
    'registerAnonymousEventHandler',
    'permSubstringTrigger',
    'getRoomUserDataKeys',
}

local streak, offered, conceded = 0, false, false

-- The alias, written once and shown twice: as a string of Lua, which is what
-- the wiki's examples look like, and as a function, which is what the second
-- one has to be because it does something with what it caught.
--
-- Long brackets rather than "\\w" and friends, so what is printed is what a
-- pattern looks like in the editor rather than what it looks like after Lua has
-- eaten a backslash.
local function luaOne(short, name)
    return string.format('lua tempAlias([[^%s$]], [[expandAlias("fetch %s")]])', short, name)
end

local function luaMany(short)
    return string.format(
        'lua tempAlias([[^%s (.+)$]], function() expandAlias("fetch " .. matches[2]) end)',
        short)
end

-- The visitor's alias, if they took the shortcut rather than the Lua. Kept so a
-- second one replaces the first rather than stacking, and so `alias off` has
-- something to kill. What they make by running the Lua themselves is the
-- client's business and not this package's: nothing here can ask what aliases
-- exist, which is exactly right.
local id, shortcut

-- What the imp hands over ------------------------------------------------------

local function concede()
    if conceded then return end
    conceded = true
    say()
    say(C.text, 'The imp comes down the ladder, which it has not done once since you ',
        'came in, and looks at you with an expression that is mostly professional ',
        'respect.')
    say(C.say, '"Three. Said properly, every one." ', C.text, 'It reaches behind it ',
        'without looking and puts a small box in your hand.')
    -- The prize is the box for the function that just won the bet, which is
    -- also the only prize this room could give that it did not invent.
    local sig = signature('tempAlias')
    say()
    say(C.text, 'On the lid, in the same small careful hand: ', manual('tempAlias'),
        C.text, '.')
    if sig then say(C.dim, '  ', sig) end
    say()
    -- What the imp says here it can actually know, which is the point of the
    -- room said out loud. An alias fires before the world's own catch-all ever
    -- runs, so a fetch that came out of one and a fetch somebody typed arrive
    -- as the same line: nothing in this package can tell them apart, and the
    -- imp claiming to would be the world colouring a word in and calling it a
    -- demonstration.
    say(C.say, '"Mind, I only ever hear what your client sends me. Whether you said all ',
        'that yourself or taught it to say them for you, I could not tell you — and that ',
        'is the whole of the trick, if you have not found it yet."')
    if not offered then
        say(C.dim, '  ', cmd('ask about the trick', 'ask about the trick',
            'the trick, which is the point of this room', C.dim))
    end
end

local function scored(good)
    if good then
        streak = streak + 1
        if streak >= BET and not conceded then concede() end
    else
        streak = 0
    end
end

-- Fetching a box ---------------------------------------------------------------
--
-- Four answers, and which one you get is worked out rather than looked up: a
-- name is on the shelf or it is not, and it is in the catalogue or it is not.
function D.fetchBox(typed)
    local name = tostring(typed or ''):gsub('^%s+', ''):gsub('%s+$', '')

    if name == '' then
        say()
        say(C.say, '"A name," ', C.text, 'the imp says, ', C.say, '"not a gesture."')
        say(C.dim, 'Try ', cmd('fetch tempAlias', 'fetch tempAlias',
            'fetch the box called tempAlias', C.dim), C.dim, '.')
        return
    end

    -- A call is not a name. Said rather than shrugged at, because typing the
    -- brackets is what somebody does when they have understood the room and
    -- got ahead of it.
    local bare = name:match('^([%w_%.]+)%s*%(')
    if bare then
        say()
        say(C.say, '"That is you using it," ', C.text, 'the imp says. ', C.say,
            '"I keep the names. What you do with one afterwards is between you and it."')
        say(C.dim, '  ', cmd('fetch ' .. bare, 'fetch ' .. bare,
            'the name, without the brackets', C.dim))
        return
    end

    local here, written = onShelf(name), signature(name)

    if here and written then
        say()
        say(C.text, 'The imp does not look down. A box comes off the shelf anyway.')
        -- The name on the lid *is* the way to the manual page for it. Printed
        -- plain and then again as the link's label, it was the same word twice.
        say(C.dim, '  ', manual(name))
        say(C.dim, '  ', written)
        scored(true)
        return
    end

    if written then
        -- In the catalogue, not in this build.
        say()
        say(C.text, 'The imp reaches, and reaches, and comes back with nothing.')
        say(C.say, '"Written up, not stocked. It is in the catalogue — ', C.text,
            'and it names the shelf it should be on, ', C.say, 'and the shelf is bare."')
        say(C.dim, '  ', written)
        say(C.dim, 'A real name, and this client has not got it. There are ',
            spell(#missing()), ' like that. ', cmd('look catalogue', 'look catalogue',
                'which ones, and why the imp is not embarrassed', C.dim), C.dim, '.')
        -- Still a true name, said properly. The bet was about saying them, not
        -- about being lucky.
        scored(true)
        return
    end

    if here then
        -- On the shelf, nobody wrote it up: Lua's own, and anything this build
        -- has that the manual does not cover.
        say()
        say(C.text, 'The imp finds it, and turns it over twice looking for the label.')
        if name:find('%.') then
            say(C.say, '"Down there? That is a shelf inside a shelf. Lua brought that one ',
                'with it — it was here before Mudlet was, and Mudlet never wrote it up ',
                'because it was never Mudlet\'s to write."')
        else
            say(C.say, '"Real enough. Nobody wrote it up, is all. Happens more than the ',
                'catalogue would like you to think."')
        end
        say(C.dim, '  ', name, '  —  on the shelf, not in the catalogue')
        scored(true)
        return
    end

    local close = similar(name)
    if close then
        say()
        say(C.say, '"Close," ', C.text, 'the imp says, delighted, ', C.say,
            '"and close is nothing. It is ', close, '. Capitals and all."')
        say(C.dim, '  ', cmd('fetch ' .. close, 'fetch ' .. close, 'with the capitals', C.dim))
        scored(false)
        return
    end

    say()
    say(C.text, 'The imp runs a finger along a shelf you cannot see the end of, and stops.')
    say(C.say, '"No such box. Not on my shelves and not in the book, and I would know ',
        'about both."')
    scored(false)
end

-- The errand, and then the trick -----------------------------------------------
--
-- The order is the demonstration, the same as the Workshop's. First the bet,
-- which the visitor tries by hand and probably loses; then the trick, once they
-- have felt why anyone would want it.
function D.errand()
    if conceded then
        say()
        say(C.say, '"Bet is settled," ', C.text, 'the imp says. ', C.say, '"Take any box ',
            'you like."')
        return
    end
    if offered then D.trick() return end
    offered = true

    say()
    say(C.text, 'The imp puts its chin on the top rung and considers you.')
    say(C.say, '"Everything here does what its name says and nothing at all if you say it ',
        'wrong, so here is a wager. Three of my names, said properly, one after another. ',
        'You will not manage two."')
    say()
    for _, name in ipairs(ASKS) do
        say(C.dim, '  ', cmd('fetch ' .. name, 'fetch ' .. name, 'say it properly', C.exit))
    end
    say()
    say(C.dim, 'Type them, if you want the wager to mean anything. Clicking is the imp ',
        'saying them for you, which is the whole argument it is about to lose.')
end

function D.trick()
    say()
    say(C.text, 'The imp leans down further than a thing that shape ought to.')
    say(C.say, '"You could keep saying them. Or you could teach the client to say them, ',
        'and say something short instead. It is the same as the trigger the clerk pays ',
        'for, only pointing the other way — that one listens to the world, this one ',
        'listens to you."')
    say()
    -- Two forms, because Mudlet takes either: a string of Lua, which is what
    -- the wiki's examples are, and a function, which the second one has to be.
    say(C.dim, 'One name, kept short:')
    local one = luaOne('rae', ASKS[1])
    say(C.dim, '  ', cmd(one, one, 'run it in this client, for real', C.exit))
    say()
    say(C.dim, 'Or one alias for every box on the shelves, which is what the brackets ',
        'in the pattern are for — whatever they catch arrives as ', C.exit, 'matches[2]',
        C.dim, ':')
    local many = luaMany('b')
    say(C.dim, '  ', cmd(many, many, 'run it in this client, for real', C.exit))
    say()
    say(C.dim, 'Then ', C.exit, 'b tempAlias', C.dim, ', or ', C.exit, 'b table.concat',
        C.dim, '. Mind what you put in the brackets: ', C.exit, '(\\w+)', C.dim,
        ' is a word and nothing else, so it catches ', C.exit, 'getPath', C.dim,
        ' and drops ', C.exit, 'table.concat', C.dim, ' on the floor — the dot is not a ',
        'word character. ', C.exit, '(.+)', C.dim, ' takes the lot.')
    say()
    say(C.dim, 'That is not the usual way, mind. In a full Mudlet you would open the ',
        link('Aliases editor', URL.aliases), C.dim, ' and fill in a pattern and a ',
        'command — no brackets, no quotes. The line above is the same alias made from ',
        'the command line, which is what packages do, and what this world has to do: ',
        'the toolbar is hidden in here.')
    say()
    say(C.dim, 'Or ', cmd('alias b', 'alias b', 'have the world make it for you', C.dim),
        C.dim, ', if you would rather not read Lua at all.')
end

-- The shortcut. Same argument as the Workshop's `trigger on gold`: on its own it
-- proves nothing, because the visitor would be typing a word this world made up
-- and taking our word for what happened next. The Lua it stood in for is
-- printed underneath, so even the lazy path shows its working.
function D.aliasFor(word)
    if word == '' then
        say(C.text, 'An alias needs something to answer to. One letter is plenty:')
        say(C.dim, '  ', cmd('alias b', 'alias b', 'make a real Mudlet alias', C.dim))
        return
    end

    D.aliasOff(true)
    shortcut = word
    id = tempAlias('^' .. word .. ' (.+)$', function()
        expandAlias('fetch ' .. matches[2])
    end)

    say()
    say(C.text, 'Mudlet takes it. From here on, ', C.exit, word, ' <name>', C.text,
        ' leaves your hands as ', C.exit, 'fetch <name>', C.text, '.')
    local many = luaMany(word)
    say(C.dim, '  ', many)
    say(C.dim, '  -> alias ', tostring(id), '   ·   ',
        cmd('alias off', 'alias off', 'kill it again', C.dim))
    say()
    say(C.dim, 'Try ', cmd(word .. ' ' .. ASKS[1], word .. ' ' .. ASKS[1],
        'the long one, the short way', C.dim), C.dim,
        '. Both lines are echoed: the one you typed, then the one your client sent.')
end

function D.aliasOff(quiet)
    if id then killAlias(id) end
    local had = shortcut
    id, shortcut = nil, nil

    if quiet then return end
    if had then
        say(C.text, 'The alias is gone. You are back to saying it all yourself.')
    else
        say(C.text, 'There is no alias to remove.')
    end
end

-- The room's furniture ---------------------------------------------------------

function D.lookShelves()
    local total = shelves()
    local written = 0
    for name in pairs(catalogue()) do
        if onShelf(name) then written = written + 1 end
    end

    say(C.text, 'Boxes to the ceiling, one to a name, in no order that helps anybody who ',
        'does not already know the name. ', thousands(total), ' of them on this side ',
        'of the room alone, which the imp will tell you it counted itself.')
    if written > 0 then
        say(C.dim, '  ', tostring(written), ' are Mudlet\'s own, out of a catalogue of ',
            tostring(SITE.functions.count), '.')
        say(C.dim, '  ', tostring(total - written), ' are Lua\'s, and were here first.')
    else
        -- No catalogue: the shelf count is still exact, and it is the number
        -- the imp trusts anyway.
        say(C.dim, '  Counted out of this client rather than off a list, which is why ',
            'the imp will stand behind it.')
    end
    say(C.dim, 'Any of them: ', cmd('fetch tempAlias', 'fetch tempAlias',
        'take a box off the shelf', C.dim), C.dim, '.')
end

function D.lookCatalogue()
    local count = tonumber(SITE.functions.count) or 0
    if count == 0 then
        say(C.text, 'A ledger on a lectern, open at a page, with nothing written on it. ',
            'The imp says the book is kept elsewhere and the wire to it is down; it does ',
            'not seem troubled, on the grounds that the shelves are right here.')
        say(C.dim, '  ', link('the same list, on the wiki', URL.functions))
        return
    end

    say(C.text, 'Not the imp\'s book — Mudlet\'s, copied out. ', tostring(count),
        ' names and, beside each, the shape of the thing: what it takes and what it ',
        'hands back. It is the same list the editor reads when it finishes your typing ',
        'for you.')

    local gone = missing()
    if #gone > 0 then
        say()
        say(C.text, spellCap(#gone), ' of the entries have no box on the shelf.')
        for _, name in ipairs(gone) do
            say(C.dim, '  ', name)
        end
        say(C.say, '"Written before they were built, or built and then not brought in ',
            'here," ', C.text, 'the imp says, unbothered. ', C.say, '"The book is the ',
            'client\'s. The shelves are this one\'s."')
    end
    say(C.dim, '  ', link('the same list, written out', URL.functions))
end

-- Who answers ------------------------------------------------------------------
--
-- A question in the Stacks reaches the imp the way one in Makers Hall reaches
-- the sage. A noun that is a true name is fetched rather than argued with: `ask
-- about tempAlias` and `fetch tempAlias` are the same question asked twice.
local function about(noun, words)
    for _, word in ipairs(words) do
        if noun:find(word, 1, true) then return true end
    end
end

function D.askImp(noun, raw)
    if noun == '' or noun == 'imp' then
        say()
        say(C.text, 'The imp puts down whatever it was counting.')
        say(C.say, '"Names. Every one of them, and what each one wants said after it. ',
            'Ask me for one properly and it is yours."')
        say(C.dim, '  ', cmd('fetch tempAlias', 'fetch tempAlias', 'take a box', C.dim),
            C.dim, '   ·   ', cmd('ask about the wager', 'ask about the wager',
                'the bet, and the trick that wins it', C.dim),
            C.dim, '   ·   ', cmd('look catalogue', 'look catalogue',
                'the book behind the shelves', C.dim))
        return
    end

    if about(noun, { 'wager', 'bet', 'work', 'job', 'errand', 'game' }) then
        D.errand()
        return
    end
    if about(noun, { 'trick', 'alias', 'short', 'cheat', 'easier' }) then
        if offered or conceded then D.trick() else D.errand() end
        return
    end
    if about(noun, { 'catalogue', 'catalog', 'book', 'list', 'index' }) then
        say()
        D.lookCatalogue()
        return
    end
    if about(noun, { 'shel', 'box', 'count', 'stack' }) then
        say()
        D.lookShelves()
        return
    end

    -- Anything else that turns out to be a real name is answered as one, in the
    -- spelling the visitor typed rather than the one the parser lowercased.
    local name = raw ~= '' and raw or noun
    if onShelf(name) or signature(name) or similar(name) then
        D.fetchBox(name)
        return
    end

    say()
    say(C.say, '"Not one of mine," ', C.text, 'the imp says. ', C.say, '"I keep names ',
        'the client answers to, and that is not one of them."')
    say(C.dim, 'The people are two doors round: ', cmd('north', 'north',
        'back to the commons', C.dim), C.dim, ', then west. Here it is ',
        cmd('fetch <name>', 'fetch tempAlias', 'take a box off the shelf', C.dim),
        C.dim, '.')
end

-- Said a beat after the room, not with it: a greeting printed inside the
-- description reads as furniture. The same reason, and the same two seconds, as
-- the sage's.
function D.greetImp()
    if D.here ~= 'stacks' then return end
    say()
    say(C.text, 'Somewhere above you the counting stops.')
    say(C.say, '"Another one who wants a thing and will not name it," ', C.text,
        'says the imp, to nobody.')
end

return { shelves = shelves, missing = missing, onShelf = onShelf, signature = signature }
