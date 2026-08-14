<?php

declare(strict_types=1);

use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Models\Category;
use Illuminate\Routing\Route;
use Illuminate\Validation\Validator;

/**
 * The cycle guard's two bail-outs cannot be reached over HTTP — route model binding
 * always hands it a Category, and the `exists` rule always resolves the parent — so
 * they are exercised against the request object directly. They exist because the
 * category tree is self-referencing and its key cascades: a category parented into its
 * own subtree would orphan a whole branch and make any walk of the tree infinite.
 *
 * @param  array<string, mixed>  $input
 */
function categoryUpdateValidator(array $input, mixed $routeCategory): Validator
{
    $request = CategoryUpdateRequest::create('/admin/categories/anything', 'PUT', $input);

    $route = new Route(['PUT'], 'admin/categories/{category}', []);
    $route->bind($request);
    $route->setParameter('category', $routeCategory);

    $request->setRouteResolver(fn (): Route => $route);

    $validator = validator($input, []);
    $request->withValidator($validator);
    $validator->passes();

    return $validator;
}

it('skips the cycle guard when the route did not resolve to a category', function (): void {
    $parent = Category::factory()->create();

    $validator = categoryUpdateValidator(['parent' => $parent->uuid], 'not-a-model');

    expect($validator->errors()->has('parent'))->toBeFalse();
});

it('skips the cycle guard when the chosen parent no longer exists', function (): void {
    $category = Category::factory()->create();

    $validator = categoryUpdateValidator(['parent' => 'a-uuid-nothing-matches'], $category);

    expect($validator->errors()->has('parent'))->toBeFalse();
});

it('still catches a real cycle when driven directly', function (): void {
    $category = Category::factory()->create();

    $validator = categoryUpdateValidator(['parent' => $category->uuid], $category);

    expect($validator->errors()->has('parent'))->toBeTrue();
});
