import React from 'react'
import { EligText } from './styles'

const EligibilityMessage = ({expPolicy, OwnerShip, ncbvoid}) => {
  return (
    <>
    { expPolicy === "yes" && OwnerShip === "yes" && !ncbvoid ? (
            <EligText>
              Since you have made claim in your existing policy & changed
              ownership, your NCB will be reset to 0%
            </EligText>
          ) : expPolicy === "yes" && OwnerShip === "yes" ? (
            <EligText>
              Since you have made claim in your existing policy & changed
              ownership, your NCB will be reset to 0%
            </EligText>
          ) : OwnerShip === "yes" ? (
            <EligText>
              Since you have changed ownership, your NCB will be reset to 0%
            </EligText>
          ) : !ncbvoid ? (
            <EligText>
              Since your policy has expired over 90 days ago, your NCB will be
              reset to 0%
            </EligText>
          ) : (
            <EligText>
              Since you have made claim in your existing policy, your NCB will
              be reset to 0%
            </EligText>
          )}
    </>
  )
}

export default EligibilityMessage