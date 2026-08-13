<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

/**
 * Categories are platform-owned reference data: vendors pick from this list and can
 * never extend it, which is what keeps browsing coherent across independently run
 * shops. Deleting one is therefore blocked while anything still hangs off it.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists categories with their product and subcategory counts', function (): void {
    $parent = Category::factory()->create(['name' => 'Groceries']);
    $child = Category::factory()->create(['name' => 'Rice', 'parent_id' => $parent->id]);

    Product::factory()->count(2)->for($child)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.categories.index', ['search' => 'Rice']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/categories/index')
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Rice')
            ->where('categories.data.0.products_count', 2)
            ->where('categories.data.0.children_count', 0)
            ->where('categories.data.0.parent.name', 'Groceries')
            ->where('filters.search', 'Rice')
            ->has('parents', 2),
        );
});

it('shows a top level category as having no parent', function (): void {
    Category::factory()->create(['name' => 'Standalone']);

    $this->actingAs($this->admin)
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('categories.data.0.parent', null));
});

it('creates a category with a slug derived from its name', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'name' => 'Fresh Produce',
            'parent' => null,
            'description' => 'Fruit and vegetables.',
            'is_active' => true,
            'sort_order' => 5,
        ])
        ->assertRedirect();

    $category = Category::query()->sole();

    expect($category->slug)->toBe('fresh-produce')
        ->and($category->parent_id)->toBeNull()
        ->and($category->description)->toBe('Fruit and vegetables.')
        ->and($category->sort_order)->toBe(5);
});

it('suffixes the slug when the name is already taken', function (): void {
    Category::factory()->create(['name' => 'Fresh Produce', 'slug' => 'fresh-produce']);
    Category::factory()->create(['name' => 'Fresh Produce', 'slug' => 'fresh-produce-2']);

    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'name' => 'Fresh Produce',
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect();

    expect(Category::query()->latest('id')->first()->slug)->toBe('fresh-produce-3');
});

it('falls back to a generic slug when the name has no slug characters', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'name' => '한국어',
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect();

    expect(Category::query()->sole()->slug)->toBe('category');
});

it('nests a new category under an existing parent', function (): void {
    $parent = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), [
            'name' => 'Beans',
            'parent' => $parent->uuid,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect();

    expect(Category::query()->where('name', 'Beans')->sole()->parent_id)->toBe($parent->id);
});

it('rejects a category with no name', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.categories.store'), ['is_active' => true, 'sort_order' => 0])
        ->assertSessionHasErrors('name');
});

it('leaves the slug alone when a category is renamed', function (): void {
    $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), [
            'name' => 'New Name',
            'is_active' => false,
            'sort_order' => 9,
        ])
        ->assertRedirect();

    $category->refresh();

    expect($category->name)->toBe('New Name')
        ->and($category->slug)->toBe('old-name')
        ->and($category->is_active)->toBeFalse()
        ->and($category->sort_order)->toBe(9);
});

it('refuses to parent a category to itself', function (): void {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'parent' => $category->uuid,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('parent');

    expect($category->fresh()->parent_id)->toBeNull();
});

it('refuses to parent a category under one of its own descendants', function (): void {
    $grandparent = Category::factory()->create();
    $parent = Category::factory()->create(['parent_id' => $grandparent->id]);
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $grandparent), [
            'name' => $grandparent->name,
            'parent' => $child->uuid,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors('parent');

    expect($grandparent->fresh()->parent_id)->toBeNull();
});

it('allows re-parenting to an unrelated category', function (): void {
    $category = Category::factory()->create();
    $newParent = Category::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'parent' => $newParent->uuid,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($category->fresh()->parent_id)->toBe($newParent->id);
});

it('skips the cycle guard when no parent was submitted', function (): void {
    $category = Category::factory()->child()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'parent' => null,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($category->fresh()->parent_id)->toBeNull();
});

it('deletes an empty category', function (): void {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect();

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('refuses to delete a category that still holds products', function (): void {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect();

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
});

it('refuses to delete a category that still has subcategories', function (): void {
    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.categories.destroy', $parent))
        ->assertRedirect();

    expect(Category::query()->whereKey($parent->id)->exists())->toBeTrue();
});
