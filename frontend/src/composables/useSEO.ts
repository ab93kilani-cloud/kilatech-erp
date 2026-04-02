// useSEO composable

export function useSEO() {
    const setMeta = (name: string, content: string) => {
        let meta = document.querySelector(`meta[name="${name}"]`)
        if (!meta) {
            meta = document.createElement('meta')
            meta.setAttribute('name', name)
            document.head.appendChild(meta)
        }
        meta.setAttribute('content', content)
    }

    const setOGMeta = (property: string, content: string) => {
        let meta = document.querySelector(`meta[property="${property}"]`)
        if (!meta) {
            meta = document.createElement('meta')
            meta.setAttribute('property', property)
            document.head.appendChild(meta)
        }
        meta.setAttribute('content', content)
    }

    const updateSEO = (data: {
        title?: string,
        description?: string,
        keywords?: string,
        image?: string,
        url?: string
    }) => {
        if (data.title) {
            document.title = `${data.title} | Kilatech Medical`
            setOGMeta('og:title', data.title)
        }

        if (data.description) {
            setMeta('description', data.description)
            setOGMeta('og:description', data.description)
        }

        if (data.keywords) {
            setMeta('keywords', data.keywords)
        }

        if (data.image) {
            setOGMeta('og:image', data.image)
        }

        if (data.url) {
            setOGMeta('og:url', data.url)
        }

        setOGMeta('og:type', 'website')
    }

    return { updateSEO }
}
