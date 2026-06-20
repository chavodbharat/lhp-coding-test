import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AttendeeController::store
 * @see app/Http/Controllers/AttendeeController.php:15
 * @route '/events/{event}/attendees'
 */
export const store = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/events/{event}/attendees',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AttendeeController::store
 * @see app/Http/Controllers/AttendeeController.php:15
 * @route '/events/{event}/attendees'
 */
store.url = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { event: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { event: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    event: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        event: typeof args.event === 'object'
                ? args.event.id
                : args.event,
                }

    return store.definition.url
            .replace('{event}', parsedArgs.event.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AttendeeController::store
 * @see app/Http/Controllers/AttendeeController.php:15
 * @route '/events/{event}/attendees'
 */
store.post = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AttendeeController::store
 * @see app/Http/Controllers/AttendeeController.php:15
 * @route '/events/{event}/attendees'
 */
    const storeForm = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AttendeeController::store
 * @see app/Http/Controllers/AttendeeController.php:15
 * @route '/events/{event}/attendees'
 */
        storeForm.post = (args: { event: string | { id: string } } | [event: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const attendees = {
    store: Object.assign(store, store),
}

export default attendees