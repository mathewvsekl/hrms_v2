import { create } from 'zustand';

const useLayoutStore = create((set) => ({
    pageTitle: '',
    pageSubtitle: '',
    backPath: null,
    setPageTitle: (title) => set({ pageTitle: title }),
    setPageSubtitle: (subtitle) => set({ pageSubtitle: subtitle }),
    setBackPath: (path) => set({ backPath: path }),
    resetPageHeader: () => set({ pageTitle: '', pageSubtitle: '', backPath: null })
}));

export default useLayoutStore;
