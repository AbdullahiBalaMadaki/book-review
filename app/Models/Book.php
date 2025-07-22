<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class Book extends Model
{
    use HasFactory;

    // Relationship: A book has many reviews
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Scope to filter by title
    public function scopeTitle(Builder $query, string $title): Builder
    {
        return $query->where('title', 'LIKE', '%' . $title . '%');
    }

    // Scope to count reviews (optionally filtered by date)
    public function scopeWithReviewsCount(Builder $query, $from = null, $to = null): Builder|QueryBuilder
    {
        return $query->withCount([
            'reviews' => function (Builder $q) use ($from, $to) {
                $this->dateRangeFilter($q, $from, $to);
            }
        ]);
    }

    // Scope to calculate average rating
    public function scopeWithAvgRating(Builder $query, $from = null, $to = null): Builder|QueryBuilder
    {
        return $query->withAvg([
            'reviews' => function (Builder $q) use ($from, $to) {
                $this->dateRangeFilter($q, $from, $to);
            }
        ], 'rating');
    }

    // Scope to order by review count
    public function scopePopular(Builder $query, $from = null, $to = null): Builder|QueryBuilder
    {
        return $query->withReviewsCount($from, $to)
                     ->orderBy('reviews_count', 'desc');
    }

    // Scope to order by average rating
    public function scopeHighestRated(Builder $query, $from = null, $to = null): Builder|QueryBuilder
    {
        return $query->withAvgRating($from, $to)
                     ->orderBy('reviews_avg_rating', 'desc');
    }

    // Filter books with at least X reviews
    public function scopeMinReviews(Builder $query, int $minReviews): Builder|QueryBuilder
    {
        return $query->withCount('reviews')
                     ->having('reviews_count', '>=', $minReviews);
    }

    // Private helper for filtering by created_at date range
    private function dateRangeFilter(Builder $query, $from = null, $to = null)
    {
        if ($from && !$to) {
            $query->where('created_at', '>=', $from);
        } elseif (!$from && $to) {
            $query->where('created_at', '<=', $to);
        } elseif ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }
    }

    // Scope for books popular in the last month
    public function scopePopularLastMonth(Builder $query): Builder|QueryBuilder
    {
        return $query->popular(now()->subMonth(), now())
                     ->highestRated(now()->subMonth(), now())
                     ->minReviews(2);
    }

    // Scope for books popular in the last 6 months
    public function scopePopularLast6Months(Builder $query): Builder|QueryBuilder
    {
        return $query->popular(now()->subMonths(6), now())
                     ->highestRated(now()->subMonths(6), now())
                     ->minReviews(5);
    }

      protected static function booted()
    {
        static::updated(
            fn(Book $book) => cache()->forget('book:' . $book->id)
        );
        static::deleted(
            fn(Book $book) => cache()->forget('book:' . $book->id)
        );
    }

    // Scope for books highly rated in the last month
    public function scopeHighestRatedLastMonth(Builder $query): Builder|QueryBuilder
    {
        return $query->highestRated(now()->subMonth(), now())
                     ->popular(now()->subMonth(), now())
                     ->minReviews(2);
    }

    // Scope for books highly rated in the last 6 months
    public function scopeHighestRatedLast6Months(Builder $query): Builder|QueryBuilder
    {
        return $query->highestRated(now()->subMonths(6), now())
                     ->popular(now()->subMonths(6), now())
                     ->minReviews(5);
    }
}
