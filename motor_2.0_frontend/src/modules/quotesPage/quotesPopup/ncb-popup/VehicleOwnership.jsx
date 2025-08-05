import React from 'react'
import { PopupSubHead } from './styles'

const VehicleOwnership = ({temp_data, register}) => {
  return (
    <>
    <PopupSubHead>
      {" "}
      Did vehicle's ownership change in the last 12 months?
    </PopupSubHead>
    <div className="vehRadioWrap">
      <input
        type="radio"
        id="ownerShipYes"
        name="ownerShip"
        value="yes"
        ref={register}
        defaultChecked={temp_data?.carOwnership === true}
      />
      <label for="ownerShipYes" style={{ cursor: "pointer" }}>
        Yes
      </label>
      <input
        type="radio"
        id="ownerShipNo"
        name="ownerShip"
        value="no"
        ref={register}
        defaultChecked={temp_data?.carOwnership === false}
      />
      <label for="ownerShipNo" style={{ cursor: "pointer" }}>
        No
      </label>
    </div>
  </>
  )
}

export default VehicleOwnership