export type LegalBundle = {
    version: string;
    label: string;
    draft: boolean;
    documents: {
        slug: string;
        title: string;
        sections: { title: string; text: string }[];
    }[];
};
