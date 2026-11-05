<?php

namespace App\Services\Gmail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Guesses which registered company a mail is from, by the sender's domain
 * (recruit / mypage URL) or by the company name.
 */
class CompanyMatcher
{
    /** Second-level suffixes where the registrable domain has three labels. */
    private const SECOND_LEVEL = ['co.jp', 'or.jp', 'ne.jp', 'ac.jp', 'go.jp', 'ad.jp', 'ed.jp', 'lg.jp', 'gr.jp', 'co.uk', 'com.au'];

    /**
     * Hosts that are shared by many companies, so they never identify one.
     */
    private const SHARED_HOSTS = [
        'rikunabi.com', 'onecareer.jp', 'offerbox.jp', 'labbase.jp', 'google.com', 'github.io',
        'notion.site', 'wantedly.com', 'en-japan.com', 'doda.jp', 'career-tasu.jp',
    ];

    /**
     * @param  array<string, int>  $companyDomains  domain => company id
     * @param  Collection<int, Company>  $companies  longest names first
     */
    public function __construct(
        private array $companyDomains,
        private Collection $companies,
    ) {
        //
    }

    public static function forUser(User $user): self
    {
        $companies = $user->companies()->get(['id', 'name', 'mypage_url', 'recruit_url']);
        $shared = [...self::atsDomains(), ...self::SHARED_HOSTS];
        $domains = [];

        foreach ($companies as $company) {
            foreach ([$company->recruit_url, $company->mypage_url] as $url) {
                $host = $url ? parse_url($url, PHP_URL_HOST) : null;

                if (! is_string($host)) {
                    continue;
                }

                $domain = self::registrableDomain(strtolower($host));

                if (! self::matchesAny($domain, $shared)) {
                    $domains[$domain] ??= $company->id;
                }
            }
        }

        // Longest names first, so "SHIFT ASIA" wins over "SHIFT".
        $companies = $companies->sortByDesc(fn (Company $c) => mb_strlen($c->name))->values();

        return new self($domains, $companies);
    }

    public function guess(string $fromAddress, ?string $fromName, string $subject): ?int
    {
        $domain = self::domainOf($fromAddress);

        foreach ($this->companyDomains as $companyDomain => $companyId) {
            if (self::matchesAny($domain, [$companyDomain])) {
                return $companyId;
            }
        }

        return $this->byName(($fromName ?? '').' '.$subject);
    }

    /**
     * The registered company whose name appears in the text, ignoring
     * spaces and "株式会社".
     */
    public function byName(string $text): ?int
    {
        $haystack = self::normalize($text);

        return $this->companies
            ->first(function (Company $c) use ($haystack) {
                $name = self::normalize($c->name);

                return mb_strlen($name) >= 2 && str_contains($haystack, $name);
            })
            ?->id;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = $this->companies->map(fn (Company $c) => $c->name)->all();
        sort($names);

        return $names;
    }

    public static function domainOf(string $address): string
    {
        return strtolower((string) substr(strrchr($address, '@') ?: '', 1));
    }

    /**
     * "recruit.example.co.jp" => "example.co.jp", "www.example.com" => "example.com".
     */
    public static function registrableDomain(string $host): string
    {
        $labels = explode('.', $host);
        $keep = in_array(implode('.', array_slice($labels, -2)), self::SECOND_LEVEL, true) ? 3 : 2;

        return implode('.', array_slice($labels, -$keep));
    }

    private static function normalize(string $text): string
    {
        $text = mb_convert_kana($text, 'as');

        return mb_strtolower((string) preg_replace('/\s+|株式会社|（株）|\(株\)/u', '', $text));
    }

    /**
     * @return list<string>
     */
    private static function atsDomains(): array
    {
        return array_values(array_map(strval(...), (array) config('shukatsu.gmail.ats_domains')));
    }

    /**
     * @param  list<string>  $domains
     */
    private static function matchesAny(string $domain, array $domains): bool
    {
        foreach ($domains as $candidate) {
            if ($domain === $candidate || str_ends_with($domain, '.'.$candidate)) {
                return true;
            }
        }

        return false;
    }
}
