## Key decisions & Trade-offs

- **Publishing.** An action raises an event, it's the one place a listing goes live, called for now by an artisan command as there wasn't a publish route. I chose this over an observer (feels hidden, skipped by bulk updates) and polling (alerts aren't fresh). *The status change is a conditional update, so a repeat or racing publish does nothing, and the event is dispatched after commit.* I allowed sold -> live, purely to cover any mistakes and to stop people having to re-list.
- **What counts as a match.** Every filter that's set needs to match. Limits are inclusive and a search needs at least one filter. 
- **Alerts**: Opted for an alert data model rather than notifications. JSON notifications can't carry a unique constraint, so any dedupe would need to live in the code. Having alerts as a table meant that I could easily enforce one alert per user per listing. 
- **Creating alerts**: A queued job runs when a listing goes live. It finds the users with a matching search and inserts one alert per user in a single query, skipping anyone already alerted (unique key). If the listing is no longer live, or has been deleted it does nothing.
- **Auth stub**: It set the user on the request only, so I couldn't use auth()->user(). I made a slight adjustment so it signs the demo user in on the guard, so I could use policies. The trade off is one user query per request, whereas before it was lazy.
- **Sync Queue**: I switched .env.example (and mine) over to use QUEUE_CONNECTION=sync so that this feature works easily on a fresh clone for review. In production, it'd be a real queue. 
- **Search page and alerts agree on the matching rule**: They apply the same logic but in opposite directions, so a test runs the same cases through both. This is to stop them drifting apart - longer term, I'd ensure there's a single definition.

## Optional / Extras

- **Backfill** - I opted against backfilling. Users see the results before they save a search, so alerting them again felt like spam/noise. Buyers may use a joint email and want those alerts backfilled, so I'd offer it as an option, off by default.
- **Duplicate Alerts** - I decided that when a listing goes live, and it matches 1 or more of the user's saved search - they will only be alerted once. This was in response to the "don't spam" comment. This is achieved with a unique constraint on listing_id and user_id. This means that if a matching listing goes live, the user is alerted, if it then goes under offer, falls through and goes back to live - the user won't be alerted again. I do think that there will be some buyers that'd want this, so I'd have this as a "back on the market" option.
- **Criteria** - I'd implement a range by allowing users to also enter minimum price. This will narrow the user's search, allowing them to cut through any noise such as auction listings, or buildings in need of severe repairs. Alongside that, I'd allow geofencing or multiple area selection.

## Where it won't hold up

- Matching reads every saved search currently. Every filter is optional, so indexes can't narrow it down. At scale, I'd enforce a filter and index by that.
- The PublishListing action technically isn't the only way to make a listing live. A listing's status is still fillable. This might happen from another dev, or an admin tool and we wouldn't send out any alerts. Could use an observer to reject this, and have a reconciliation job as a safety net. 
- There's one insert per listing. If a listing goes live and it matches a big number of searches, that's a large write, it'd be better to be split into batches by user ID range. 

## Left out / with more time

- Alert delivery is left out, the alert just appears at the minute. I'd add a notifed_at column to the alerts, and then deliver the alert to the chosen notification channel. Having users decide between instant/hourly and daily alerts would be a nice addition too, as this helps with the "don't spam" requirement. 
- Recording which searches matched each alert, which would then be shown on the alert itself (and notification). 
- Bulk publishing listings, for this solution I could just accept multiple references in the command and run the action for each. That'd keep alerts as one job per listing so each retries on its own. If delivery was daily or hourly, then this wouldn't spam anyone.

## How I used AI

I drafted the design, and then used Claude to challenge it before building. Some of my ideas held up (publish action over an observer) and some changed (single query approach when creating alerts). I built with Claude one slice at a time, reviewed every diff and tested. I removed some scope creep. 