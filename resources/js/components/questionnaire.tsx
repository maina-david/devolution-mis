import { CheckCircle2, CircleAlert, Save } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Marker, MarkerContent, MarkerIcon } from '@/components/ui/marker';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';

export type QuestionnaireQuestion = {
    id: string;
    name: string;
    label: string;
    type: 'number' | 'text' | 'textarea';
    required?: boolean;
    min?: number;
    max?: number;
    step?: number;
    minLength?: number;
    placeholder?: string;
    visibleWhen?: { questionId: string; equals: string };
};

export default function Questionnaire({
    storageKey,
    questions,
    evidenceComplete,
    progressLabel,
    autosavedLabel,
    evidenceReadyLabel,
    evidenceRequiredLabel,
}: {
    storageKey: string;
    questions: QuestionnaireQuestion[];
    evidenceComplete: boolean;
    progressLabel: (complete: number, total: number) => string;
    autosavedLabel: string;
    evidenceReadyLabel: string;
    evidenceRequiredLabel: string;
}) {
    const [answers, setAnswers] = useState<Record<string, string>>(() => {
        if (typeof window === 'undefined') {
            return {};
        }

        const retained = window.localStorage.getItem(storageKey);

        if (!retained) {
            return {};
        }

        try {
            return JSON.parse(retained) as Record<string, string>;
        } catch {
            window.localStorage.removeItem(storageKey);

            return {};
        }
    });

    useEffect(() => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(storageKey, JSON.stringify(answers));
        }
    }, [answers, storageKey]);

    const visibleQuestions = useMemo(
        () =>
            questions.filter(
                (question) =>
                    !question.visibleWhen ||
                    answers[question.visibleWhen.questionId] ===
                        question.visibleWhen.equals,
            ),
        [answers, questions],
    );
    const requiredQuestions = visibleQuestions.filter(
        (question) => question.required,
    );
    const completed = requiredQuestions.filter(
        (question) => (answers[question.id] ?? '').trim() !== '',
    ).length;
    const percentage =
        requiredQuestions.length === 0
            ? 100
            : Math.round((completed / requiredQuestions.length) * 100);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-3">
                <Progress value={percentage} className="flex-1" />
                <span className="text-xs text-muted-foreground">
                    {progressLabel(completed, requiredQuestions.length)}
                </span>
            </div>
            {visibleQuestions.map((question) => {
                const shared = {
                    id: question.id,
                    name: question.name,
                    required: question.required,
                    value: answers[question.id] ?? '',
                    placeholder: question.placeholder,
                    'aria-label': question.label,
                    onChange: (
                        event: React.ChangeEvent<
                            HTMLInputElement | HTMLTextAreaElement
                        >,
                    ) =>
                        setAnswers((current) => ({
                            ...current,
                            [question.id]: event.target.value,
                        })),
                };

                if (question.type === 'textarea') {
                    return (
                        <Textarea
                            key={question.id}
                            {...shared}
                            minLength={question.minLength}
                        />
                    );
                }

                return (
                    <Input
                        key={question.id}
                        {...shared}
                        type={question.type}
                        min={question.min}
                        max={question.max}
                        step={question.step}
                        minLength={question.minLength}
                    />
                );
            })}
            <div className="grid gap-1 sm:grid-cols-2">
                <Marker>
                    <MarkerIcon>
                        <Save />
                    </MarkerIcon>
                    <MarkerContent>{autosavedLabel}</MarkerContent>
                </Marker>
                <Marker>
                    <MarkerIcon>
                        {evidenceComplete ? <CheckCircle2 /> : <CircleAlert />}
                    </MarkerIcon>
                    <MarkerContent>
                        {evidenceComplete
                            ? evidenceReadyLabel
                            : evidenceRequiredLabel}
                    </MarkerContent>
                </Marker>
            </div>
        </div>
    );
}
