-- The News Room, north of the front page: /news/ and the posts on it.

local SITE = require('mudlet-demo.site')
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd
local spellCap = core.spellCap

return {
    title = 'The News Room',
    desc = function()
        return 'A small office that is almost entirely cork. Notices go up faster than '
            .. 'anyone takes them down and the layers have gone geological — the bottom '
            .. 'of the board is from 2008. A drawer beneath is labelled ARCHIVE, amended '
            .. 'in a second pen to ' .. SITE.news.count .. ' AND RISING.'
    end,
    exits = { south = 'home' },
    things = {
        {
            name = 'the notice board',
            keys = { 'board', 'notice board', 'noticeboard', 'notices', 'news' },
            -- Whatever the site has posted most recently, in the site's own
            -- order. The dates are right-aligned into a fixed column so the
            -- headlines line up whether the day is one digit or two.
            look = function()
                local posts = SITE.news.posts
                if #posts == 0 then
                    say(C.text, 'The board is bare, which has never happened before.')
                    return
                end
                say(C.text, spellCap(#posts), ' notices near the top, still crisp:')
                say()
                for _, post in ipairs(posts) do
                    say(C.dim, ('%11s  '):format(post.date), link(post.title, post.url))
                    local by = post.author ~= '' and post.author or nil
                    local blurb = post.blurb ~= '' and post.blurb or nil
                    if by or blurb then
                        say(C.text, '    ' .. (by or ''), C.desc,
                            blurb and ((by and ' — ' or '    ') .. blurb) or '')
                    end
                end
            end,
        },
        {
            name = 'the archive drawer',
            keys = { 'drawer', 'archive', 'label' },
            url = URL.news,
            look = function()
                say(C.text, 'It opens much further than a drawer that size should. Every release ',
                    'since 2008 is in here, in order, and somebody has plainly been ',
                    'keeping it that way.')
                say(C.dim, 'The whole of it: ', link('mudlet.org/news', URL.news), C.dim .. '.')
            end,
        },
    },
}
