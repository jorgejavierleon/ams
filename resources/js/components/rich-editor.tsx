import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Braces,
    Heading2,
    Italic,
    List,
    ListOrdered,
    Quote,
    Redo,
    Undo,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Toggle } from '@/components/ui/toggle';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

export type DocumentVariable = {
    id: number;
    name: string;
    key: string;
    description: string | null;
};

type Props = {
    value: string;
    onChange: (html: string) => void;
    variables: DocumentVariable[];
    placeholder?: string;
    editorId?: string;
};

/**
 * A headless Tiptap rich-text editor with a formatting toolbar and an "Insert
 * Variable" picker. The picker lists the organization's document variables in a
 * cmdk command palette and drops the selected `{{token}}` at the caret; those
 * tokens are resolved to the employee's data when the document is published.
 */
export function RichEditor({
    value,
    onChange,
    variables,
    placeholder,
    editorId,
}: Props) {
    // Tiptap builds its editor instance imperatively; the React Compiler must
    // not memoize around it, and SSR needs deferred rendering to avoid
    // hydration mismatches.
    'use no memo';

    const { t } = useTranslations();
    const [pickerOpen, setPickerOpen] = useState(false);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [1, 2, 3] },
            }),
        ],
        content: value,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                id: editorId ?? '',
                class: 'min-h-48 px-3 py-2 text-sm',
                'data-placeholder': placeholder ?? '',
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    // Keep the editor in sync when the value is replaced from outside (e.g. a
    // form reset), without disturbing the caret during normal typing.
    useEffect(() => {
        if (editor && value !== editor.getHTML()) {
            editor.commands.setContent(value, { emitUpdate: false });
        }
        // Only react to external value changes, not editor identity.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    if (!editor) {
        return null;
    }

    function insertVariable(key: string) {
        editor?.chain().focus().insertContent(`${key} `).run();
        setPickerOpen(false);
    }

    return (
        <div className="rounded-md border border-input bg-transparent shadow-xs focus-within:ring-1 focus-within:ring-ring">
            <div className="flex flex-wrap items-center gap-1 border-b border-input p-1">
                <Toggle
                    size="sm"
                    pressed={editor.isActive('bold')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBold().run()
                    }
                    aria-label={t('ui.documents.editor.bold')}
                >
                    <Bold className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('italic')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleItalic().run()
                    }
                    aria-label={t('ui.documents.editor.italic')}
                >
                    <Italic className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('heading', { level: 2 })}
                    onPressedChange={() =>
                        editor.chain().focus().toggleHeading({ level: 2 }).run()
                    }
                    aria-label={t('ui.documents.editor.heading')}
                >
                    <Heading2 className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('bulletList')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                    aria-label={t('ui.documents.editor.bullet_list')}
                >
                    <List className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('orderedList')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleOrderedList().run()
                    }
                    aria-label={t('ui.documents.editor.ordered_list')}
                >
                    <ListOrdered className="size-4" />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('blockquote')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBlockquote().run()
                    }
                    aria-label={t('ui.documents.editor.quote')}
                >
                    <Quote className="size-4" />
                </Toggle>

                <span className="mx-1 h-5 w-px bg-border" aria-hidden="true" />

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-8"
                    onClick={() => editor.chain().focus().undo().run()}
                    aria-label={t('ui.documents.editor.undo')}
                >
                    <Undo className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-8"
                    onClick={() => editor.chain().focus().redo().run()}
                    aria-label={t('ui.documents.editor.redo')}
                >
                    <Redo className="size-4" />
                </Button>

                <Popover open={pickerOpen} onOpenChange={setPickerOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="ml-auto h-8"
                        >
                            <Braces className="size-4" />
                            {t('ui.documents.editor.insert_variable')}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-80 p-0" align="end">
                        <Command>
                            <CommandInput
                                placeholder={t(
                                    'ui.documents.editor.variable_search',
                                )}
                            />
                            <CommandList>
                                <CommandEmpty>
                                    {t('ui.documents.editor.variable_empty')}
                                </CommandEmpty>
                                <CommandGroup>
                                    {variables.map((variable) => (
                                        <CommandItem
                                            key={variable.id}
                                            value={`${variable.name} ${variable.key}`}
                                            onSelect={() =>
                                                insertVariable(variable.key)
                                            }
                                            className="flex flex-col items-start gap-0.5"
                                        >
                                            <span className="font-medium">
                                                {variable.name}
                                            </span>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {variable.key}
                                            </span>
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>

            <EditorContent editor={editor} className={cn('tiptap-wrapper')} />
        </div>
    );
}
