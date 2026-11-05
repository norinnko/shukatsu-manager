import type { Certification, Education } from '@/types';

export type YmFormat = 'kanji' | 'kanji-padded' | 'slash' | 'dash';

export const YM_FORMATS: { value: YmFormat; label: string }[] = [
    { value: 'kanji', label: '2024年4月' },
    { value: 'kanji-padded', label: '2024年04月' },
    { value: 'slash', label: '2024/04' },
    { value: 'dash', label: '2024-04' },
];

export function formatYm(ym: string | null | undefined, format: YmFormat): string {
    if (!ym) {
        return '';
    }

    const [y, m] = ym.split('-');

    switch (format) {
        case 'kanji':
            return `${y}年${Number(m)}月`;
        case 'kanji-padded':
            return `${y}年${m}月`;
        case 'slash':
            return `${y}/${m}`;
        case 'dash':
            return `${y}-${m}`;
    }
}

/** "大阪学院大学 経済学部 経済学科" */
export function schoolFullName(e: Education): string {
    return [e.school, e.faculty, e.department, e.major].filter(Boolean).join(' ');
}

/** Timeline rows: entry and graduation, oldest first. */
export function educationTimeline(educations: Education[], format: YmFormat, newestFirst = false): string {
    const rows: { ym: string; text: string }[] = [];

    for (const e of educations) {
        const name = schoolFullName(e);
        rows.push({ ym: e.entered_on, text: `${formatYm(e.entered_on, format)}　${name}　入学` });

        if (e.graduated_on) {
            const verb = e.graduation_status === 'expected' ? '卒業見込み' : '卒業';
            rows.push({ ym: e.graduated_on, text: `${formatYm(e.graduated_on, format)}　${name}　${verb}` });
        }
    }

    rows.sort((a, b) => a.ym.localeCompare(b.ym));

    if (newestFirst) {
        rows.reverse();
    }

    return rows.map((r) => r.text).join('\n');
}

export function certificationsText(certifications: Certification[], format: YmFormat, separator: 'newline' | 'comma'): string {
    const items = certifications.map((c) => {
        const when = c.acquired_on ? `（${formatYm(c.acquired_on, format)}）` : '';
        const score = c.score ? ` ${c.score}` : '';

        return `${c.name}${score}${when}`;
    });

    return items.join(separator === 'newline' ? '\n' : '、');
}
