import React from 'react'
import { PopupSubHead } from './styles'

const ClaimsMade = ({temp_data, register}) => {
  return (
    <>
    {" "}
    <PopupSubHead>
      Any claims made in your existing policy?
    </PopupSubHead>
    <div className="vehRadioWrap">
      <input
        type="radio"
        id="claimMadeYes"
        name="claimMade"
        value="yes"
        ref={register}
        defaultChecked={
          temp_data?.expPolicy === false ||
          temp_data?.noClaimMade === false
        }
      />
      <label for="claimMadeYes" style={{ cursor: "pointer" }}>
        Yes
      </label>
      <input
        type="radio"
        id="ownerNo"
        name="claimMade"
        value="no"
        ref={register}
        defaultChecked={
          temp_data?.expPolicy === true ||
          temp_data?.noClaimMade === true
        }
      />
      <label for="ownerNo" style={{ cursor: "pointer" }}>
        No
      </label>
    </div>
  </>
  )
}

export default ClaimsMade