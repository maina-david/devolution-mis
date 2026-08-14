import { Loader2Icon } from "lucide-react"

import { cn } from "@/lib/utils"
import { useCommonCopy } from "@/hooks/use-localization"

function Spinner({ className, ...props }: React.ComponentProps<"svg">) {
  const copy = useCommonCopy()

  return (
    <Loader2Icon
      role="status"
      aria-label={copy.loading}
      className={cn("size-4 animate-spin", className)}
      {...props}
    />
  )
}

export { Spinner }
